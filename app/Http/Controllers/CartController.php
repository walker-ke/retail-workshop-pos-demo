<?php

namespace App\Http\Controllers;

use DB;
use Cart;
use App\Models\Orders;
use App\Models\Products;
use App\Models\OrderDetails;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;

class CartController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    // Snapshot of the current cart, sent back to the POS screen after every
    // AJAX mutation so the receipt table/summary can be re-rendered in place
    // instead of reloading the whole page.
    private function cartPayload()
    {
        $items = Cart::content()->values()->map(function ($item) {
            return [
                'rowId' => $item->rowId,
                'name' => $item->name,
                'qty' => $item->qty,
                'price' => $item->price,
                'amount' => $item->price * $item->qty,
            ];
        });

        return [
            'items' => $items,
            'hasItems' => $items->count() > 0,
            'subtotal' => Cart::subtotal(),
            'tax' => Cart::tax(),
            'total' => Cart::total(),
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:products,id', 'name' => 'required|string|max:255', 'quantity' => 'required|integer|min:1|max:999999', 'price' => 'required|numeric|min:0']);
        $shopId = auth()->user()->activeShopId();

        if (!$shopId) {
            $message = 'You are viewing all shops. Switch into one shop before selling.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }
            Alert::error('Select a Shop', $message);
            return redirect()->route('pos');
        }

        $product = Products::find($request->id);

        if ($product) {
            $onHand = (int) DB::table('product_stock')
                ->where('product_id', $product->id)
                ->where('shop_id', $shopId)
                ->value('quantity');

            if ($onHand <= 0 && !auth()->user()->hasAccess('zero_stock_quantity_sale')) {
                $message = 'This product is out of stock at your shop and you do not have permission to sell it.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $message], 422);
                }
                return redirect()->back()->with('error', $message);
            }
        }

        $data = array();
        $data['id'] = $request->id;
        $data['name'] = $product->product_name;
        $data['qty'] = $request->quantity;
        $data['price'] = auth()->user()->hasAccess('edit_prices') ? $request->price : $product->price;
        $data['options'] = ['code' => $product->product_code ?? '', 'shop_id' => $shopId];

        Cart::add($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product added to cart.', 'cart' => $this->cartPayload()]);
        }

        return redirect()->back()->with('success', 'Product Added to Cart');
    }

    public function updateCart(Request $request, $rowId)
    {
        $request->validate(['qty' => 'required|integer|min:1|max:999999', 'price' => 'sometimes|numeric|min:0']);
        try {
            if($request->has('price')){
                if (!auth()->user()->hasAccess('edit_prices')) {
                    $item = Cart::get($rowId);
                    $product = Products::findOrFail($item->id);
                    $request->merge(['price' => $product->price]);
                }
                Cart::update($rowId, [
                    'qty' => $request->qty,
                    'price' => $request->price,
                ]);
            }else{
                Cart::update($rowId, $request->qty);
            }
        } catch (InvalidRowIDException $e) {
            // The line was already removed (e.g. voided from another tab/device)
            // before this edit was committed - resync instead of erroring out.
            if ($request->wantsJson()) {
                return response()->json(['message' => 'That item was already removed from the cart.', 'cart' => $this->cartPayload()]);
            }
            return redirect()->back()->with('error', 'That item was already removed from the cart.');
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cart updated.', 'cart' => $this->cartPayload()]);
        }

        return redirect()->back()->with('success', 'Cart Updated');
    }

    public function deleteCart(Request $request, $rowId)
    {
        try {
            Cart::remove($rowId);
        } catch (InvalidRowIDException $e) {
            // Already gone - treat as success rather than erroring out.
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product removed.', 'cart' => $this->cartPayload()]);
        }

        return redirect()->back()->with('success', 'Product Removed');
    }

    public function deleteAll(Request $request)
    {
        Cart::destroy();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cart cleared.', 'cart' => $this->cartPayload()]);
        }

        return redirect()->back()->with('success', 'Cart Deleted');
    }

    public function createInvoice(Request $request)
    {
        if (!auth()->user()->activeShopId()) {
            Alert::error('Select a Shop', 'You are viewing all shops. Switch into one shop before selling.');
            return redirect()->route('pos');
        }

        if (Cart::count() == 0) {
            Alert::error('Cart is Empty', 'Add a product to the cart before checking out.');
            return redirect()->route('pos');
        }

        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
        ]);

        $customer_id = $request->customer_id;
        $customer = $customer_id ? DB::table('customers')->where('id', $customer_id)->first() : null;

        if (!$customer) {
            $customer = (object) [
                'id' => null,
                'customer_name' => \App\Support\ReceiptData::customerName($request->customer_name),
                'customer_email' => null,
            ];
        }

        $content = Cart::content();
        $paymentTypes = PaymentType::where('enabled', true)->orderBy('position')->orderBy('name')->get();
        $combinedAvailable = $paymentTypes->contains(fn ($type) => strcasecmp($type->name, 'Cash') === 0)
            && $paymentTypes->contains(fn ($type) => strcasecmp($type->name, 'Paybill') === 0);
        $selectedPaymentMethod = $paymentTypes->firstWhere('name', $request->payment_method)
            ? $request->payment_method
            : optional($paymentTypes->first())->name;

        if ($combinedAvailable && $request->payment_method === '__combined') $selectedPaymentMethod = '__combined';
        $shop = \App\Models\Shop::findOrFail(auth()->user()->activeShopId());
        $receipt = \App\Support\ReceiptData::preview($shop, $customer, $content, $selectedPaymentMethod, auth()->user(), Cart::total());
        return view('invoice.invoice', compact('customer', 'content', 'paymentTypes', 'selectedPaymentMethod', 'receipt', 'combinedAvailable'));
    }

    public function addTransaction(Request $request)
    {
        $cashier = $request->user();
        $shopId = $cashier->activeShopId();
        if (!$shopId || Cart::count() == 0) {
            return redirect()->route('pos')->with('error', 'Select a shop and add a product before completing a sale.');
        }
        if (Cart::content()->contains(fn ($item) => (int) ($item->options->shop_id ?? 0) !== (int) $shopId)) {
            Cart::destroy();
            return redirect()->route('pos')->with('error', 'The shop changed since these items were added. Add the items again for the selected shop.');
        }
        if (!$cashier->hasAccess('edit_prices')) {
            foreach (Cart::content() as $item) {
                $catalogProduct = Products::findOrFail($item->id);
                Cart::update($item->rowId, ['price' => $catalogProduct->price]);
            }
        }
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'paid_amount' => 'required_unless:payment_method,__combined|nullable|numeric|min:0',
            'cash_amount' => 'required_if:payment_method,__combined|nullable|numeric|min:0',
            'paybill_amount' => 'required_if:payment_method,__combined|nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return redirect()->route('pos')->withErrors($validator)->withInput();
        }
        $breakdown = null;
        $paymentType = PaymentType::where('enabled', true)->where('name', $request->payment_method)->first();
        if ($request->payment_method === '__combined') {
            $types = PaymentType::where('enabled', true)->get();
            $cashType = $types->first(fn ($type) => strcasecmp($type->name, 'Cash') === 0);
            $paybillType = $types->first(fn ($type) => strcasecmp($type->name, 'Paybill') === 0);
            if (!$cashType || !$paybillType) return redirect()->route('pos')->withErrors(['payment' => 'Cash and Paybill must both be enabled for combined payment.']);
            try {
                $breakdown = \App\Support\CombinedPayment::breakdown(Cart::total(), $request->cash_amount, $request->paybill_amount);
            } catch (\Illuminate\Validation\ValidationException $error) {
                return redirect()->route('pos')->withErrors($error->errors())->withInput();
            }
            $paymentType = new PaymentType([
                'name' => 'Combined payment', 'change_allowed' => false,
                'customer_required' => $cashType->customer_required || $paybillType->customer_required,
                'mark_transaction_as_paid' => $cashType->mark_transaction_as_paid && $paybillType->mark_transaction_as_paid,
            ]);
        }
        if (!$paymentType) {
            return redirect()->route('pos')->withErrors(['payment_method' => 'Choose an enabled payment method.']);
        }
        if ($paymentType->customer_required && !$request->customer_id && \App\Support\ReceiptData::customerName($request->customer_name) === 'Walk-in Customer') {
            return redirect()->route('pos')->withErrors(['customer_id' => 'This payment method requires a customer.']);
        }
        $total = \App\Support\ReceiptData::amount(Cart::total());
        try {
            $paid = $breakdown ? $total : \App\Support\CombinedPayment::tender(Cart::total(), $request->paid_amount, (bool) $paymentType->change_allowed, (bool) $paymentType->mark_transaction_as_paid);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return redirect()->route('pos')->withErrors($error->errors())->withInput();
        }
        $customer = $request->customer_id ? DB::table('customers')->where('id', $request->customer_id)->first() : null;
        $shop = \App\Models\Shop::findOrFail($shopId);
        $contents = Cart::content();
        $products = Products::whereIn('id', $contents->pluck('id'))->get()->keyBy('id');
        $data = [
            'shop_id' => $shopId,
            'cashier_id' => $cashier->id, 'cashier_name' => $cashier->name,
            'receipt_settings' => json_encode(\App\Support\ReceiptData::business($shop), JSON_THROW_ON_ERROR),
            'customer_id' => $customer->id ?? null, 'customer_name' => $customer->customer_name ?? \App\Support\ReceiptData::customerName($request->customer_name),
            'order_date' => now('Africa/Nairobi')->format('d/m/y'), 'order_status' => $paymentType->mark_transaction_as_paid ? 'Paid' : 'Pending',
            'total_products' => Cart::count(), 'sub_total' => \App\Support\ReceiptData::amount(Cart::subtotal()),
            'tax' => \App\Support\ReceiptData::amount(Cart::tax()), 'total' => $total,
            'payment_method' => $paymentType->name, 'paid_amount' => $paid,
            'payment_breakdown' => $breakdown ? json_encode($breakdown, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $orderId = \App\Support\StockTransactions::run(function () use ($data, $contents, $products, $shopId) {
            \App\Support\StockTransactions::lock($contents->pluck('id')->all(), $shopId);
            do { $data['transaction_code'] = mt_rand(100000000, 999999999); } while ($this->transactionCodeExists($data['transaction_code']));
            $orderId = DB::table('orders')->insertGetId($data);
            foreach ($contents as $item) {
                $product = $products->get($item->id);
                DB::table('order_details')->insert([
                    'order_id' => $orderId, 'product_id' => $item->id,
                    'product_name' => $product->product_name ?? $item->name,
                    'product_code' => $product->product_code ?? $item->options->code ?? null,
                    'quantity' => $item->qty, 'unit_price' => $item->price, 'total' => $item->price * $item->qty,
                ]);
                $stock = DB::table('product_stock')->where('product_id', $item->id)->where('shop_id', $shopId)->first();
                if ((int) ($stock->reserved_quantity ?? 0) > 0 && (int) $stock->quantity - (int) ($stock->reserved_quantity ?? 0) < $item->qty) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['stock'=>'Some of this item is reserved for a shop transfer. Reduce the sale quantity.']);
                }
                DB::table('product_stock')->where('product_id', $item->id)->where('shop_id', $shopId)->decrement('quantity', $item->qty);
            }
            return $orderId;
        });
        Cart::destroy();
        return redirect()->route('view_receipt', $orderId)->with('success', 'Sale saved. Your receipt is ready to print.');
    }
    public function transactionCodeExists($number){
        return Orders::whereTransactionCode($number)->exists();
    }
}
