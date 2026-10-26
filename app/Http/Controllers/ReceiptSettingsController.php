<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class ReceiptSettingsController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    public function index(Request $request)
    {
        abort_unless($request->user()->isTopAdmin(), 403);
        $shops = Shop::orderBy('name')->get();
        $selected = $request->query('shop_id', $request->user()->activeShopId() ?: $shops->first()?->id);
        $shop = $shops->firstWhere('id', $selected);
        abort_unless($shop, 404);
        return view('receipts.settings', compact('shops', 'shop'));
    }

    public function update(Request $request, $id)
    {
        abort_unless($request->user()->isTopAdmin(), 403);
        $shop = Shop::findOrFail($id);
        $rules = ['receipt_name' => 'required|string|max:255'];
        foreach (['pin', 'phone', 'address', 'paybill', 'account_number', 'till_number', 'footer'] as $field) {
            $rules['receipt_'.$field] = 'nullable|string|max:255';
        }
        $data = $request->validate($rules);
        foreach ($data as $field => $value) $shop->{$field} = $value;
        $shop->save();
        return redirect()->route('receipt_settings', ['shop_id' => $shop->id])->with('success', 'Receipt settings saved. New sales will use these details.');
    }
}
