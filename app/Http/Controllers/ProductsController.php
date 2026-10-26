<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\Brands;
use App\Models\Shop;
use DB;

class ProductsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }


    //STORE BRANDS
    public function addBrand(Request $request)
    {
        $brand = new Brands;
        $brand->brand_name = $request->input('brand_name');
        $brand->manufacturer = $request->input('manufacturer');

        $brand->save();

        return redirect()->back()->with('success', 'Brand Added Successfully');
    }
    //EDIT BRANDS
    public function editBrand(Request $request, Brands $brands)
    {
        $brand_id = $request->input('brand_id');

        $brand = Brands::findOrFail($brand_id);

        $brand->brand_name = $request->input('brand_name');
        $brand->manufacturer = $request->input('manufacturer');

        $brand->update();

        return redirect()->back()->with('success', 'Brand Updated Successfully');
    }
    //DELETE BRANDS
    public function deleteBrand(Request $request)
    {
        $delBrand_id = $request->input('delete_brand');

        $delBrand = Brands::findOrFail($delBrand_id);

        $delBrand->delete();

        return redirect()->back()->with('success', 'Brand Deleted Successfully');
    }


    /**
     * Per-product on-hand quantity for the given shop, or summed across all
     * shops when $shopId is null (the top admin's "all shops" view).
     */
    private function stockMap(?int $shopId)
    {
        $query = DB::table('product_stock')
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id');

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->pluck('quantity', 'product_id');
    }

    //STORATION OF PRODUCTS ONLY
    public function store(Request $request)
    {
        $request->validate([
            'product_name' => 'required|max:255',
            'product_code' => 'required|string|max:255|unique:products,product_code',
            'brand' => 'required',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'shop_id' => 'nullable|exists:shops,id',
        ]);

        $shopId = auth()->user()->activeShopId() ?: $request->shop_id;

        if (!$shopId) {
            return redirect()->route('list_product')->with('error', 'Select which shop this product\'s initial stock belongs to.');
        }

        $data = [
            'product_name' => $request->product_name,
            'product_code' => $request->product_code,
            'description' => $request->description,
            'brand' => $request->brand,
            'price' => $request->price,
            'cost_price' => $request->cost_price ?? 0,
            'legacy_quantity' => 0,
        ];

        return DB::transaction(function () use ($data, $shopId, $request) {
            $productId = DB::table('products')->insertGetId($data);

            if ($productId) {
                DB::table('product_stock')->insert([
                    'product_id' => $productId,
                    'shop_id' => $shopId,
                    'quantity' => $request->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->route('list_product')->with('success', 'Product Added Successfully');
            }

            return redirect()->route('list_product')->with('error', 'Product Added Failed');
        });
    }

    //PRODUCT LISTS
    public function listOfProducts(Request $request)
    {
        $selectedBrand = $request->query('brand');
        $shopId = auth()->user()->activeShopId();

        $query = Products::query();
        if ($selectedBrand) {
            $query->where('brand', $selectedBrand);
        }

        $prod = $query->orderBy('product_name')->get();
        $stockByProduct = $this->stockMap($shopId);
        $prod->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });

        $product = $prod->count();
        $brand = Brands::orderBy('brand_name')->get();
        $shops = Shop::where('is_active', true)->orderBy('name')->get();

        return view('products.list_products', compact('prod', 'product', 'brand', 'selectedBrand', 'shops', 'shopId'));
    }

    //DOWNLOAD A CSV TEMPLATE FOR IMPORTING PRODUCTS
    public function importTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products-import-template.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Group', 'Cost Price', 'Sale Price', 'Quantity']);
            fputcsv($handle, ['SAMPLE001', 'Sample Product', 'Sample Group', '200', '350', '10']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    //IMPORT PRODUCTS FROM A CSV FILE (creates new products, updates existing ones matched by Code)
    public function importProducts(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:10240',
            'shop_id' => 'nullable|exists:shops,id',
        ]);

        $shopId = auth()->user()->activeShopId() ?: $request->shop_id;

        if (!$shopId) {
            return redirect()->route('list_product')->with('error', 'Select which shop this import\'s quantities belong to.');
        }

        $handle = fopen($request->file('import_file')->getRealPath(), 'r');
        if (!$handle) {
            return redirect()->route('list_product')->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return redirect()->route('list_product')->with('error', 'The file is empty.');
        }

        $columnMap = [
            'code' => ['code', 'product code', 'product_code', 'sku'],
            'name' => ['name', 'product name', 'product_name'],
            'group' => ['group', 'brand'],
            'cost_price' => ['cost price', 'cost_price', 'cost'],
            'price' => ['sale price', 'price', 'sale_price'],
            'quantity' => ['quantity', 'qty'],
        ];

        $normalizedHeader = array_map(function ($h) {
            return strtolower(trim($h));
        }, $header);

        $colIndex = [];
        foreach ($columnMap as $key => $aliases) {
            foreach ($aliases as $alias) {
                $pos = array_search($alias, $normalizedHeader, true);
                if ($pos !== false) {
                    $colIndex[$key] = $pos;
                    break;
                }
            }
        }

        if (!isset($colIndex['code']) || !isset($colIndex['name'])) {
            fclose($handle);
            return redirect()->route('list_product')->with('error', 'The file must have "Code" and "Name" columns. Download the template for the expected format.');
        }

        try {
            app(\App\Services\BackupService::class)->create('pre-import');
        } catch (\Throwable $e) {
            fclose($handle);
            report($e);
            return redirect()->route('list_product')->with('error', 'Import stopped because its safety backup failed. Check Settings → Backups.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $knownBrands = Brands::pluck('brand_name')->map(fn ($b) => strtolower(trim($b)))->flip();

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $code = isset($colIndex['code']) ? trim((string) ($row[$colIndex['code']] ?? '')) : '';
                $name = isset($colIndex['name']) ? trim((string) ($row[$colIndex['name']] ?? '')) : '';

                if ($code === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                $group = isset($colIndex['group']) ? trim((string) ($row[$colIndex['group']] ?? '')) : '';
                $costPrice = isset($colIndex['cost_price']) ? (float) ($row[$colIndex['cost_price']] ?? 0) : 0;
                $price = isset($colIndex['price']) ? (float) ($row[$colIndex['price']] ?? 0) : 0;
                $quantity = isset($colIndex['quantity']) ? (int) ($row[$colIndex['quantity']] ?? 0) : 0;

                if ($group !== '' && !isset($knownBrands[strtolower($group)])) {
                    Brands::create(['brand_name' => $group, 'manufacturer' => '']);
                    $knownBrands[strtolower($group)] = true;
                }

                $data = [
                    'product_name' => $name,
                    'brand' => $group,
                    'price' => $price,
                    'cost_price' => $costPrice,
                ];

                $existing = DB::table('products')->where('product_code', $code)->first();
                if ($existing) {
                    DB::table('products')->where('id', $existing->id)->update($data);
                    $productId = $existing->id;
                    $updated++;
                } else {
                    $data['product_code'] = $code;
                    $data['legacy_quantity'] = 0;
                    $productId = DB::table('products')->insertGetId($data);
                    $created++;
                }

                DB::table('product_stock')->updateOrInsert(
                    ['product_id' => $productId, 'shop_id' => $shopId],
                    ['quantity' => $quantity]
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            report($e);
            return redirect()->route('list_product')->with('error', 'Import failed. Check the file format and try again.');
        }

        fclose($handle);

        return redirect()->route('list_product')->with('success', "Import complete: {$created} created, {$updated} updated" . ($skipped ? ", {$skipped} rows skipped (missing code or name)" : '') . '.');
    }

    //EXPORT PRODUCTS AS CSV
    public function exportProducts(Request $request)
    {
        $selectedBrand = $request->query('brand');
        $shopId = auth()->user()->activeShopId();

        $query = Products::query();
        if ($selectedBrand) {
            $query->where('brand', $selectedBrand);
        }
        $products = $query->orderBy('product_name')->get();
        $stockByProduct = $this->stockMap($shopId);
        $products->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });

        $canViewCost = auth()->user()->hasAccess('view_cost_prices');
        $filename = 'products-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($products, $canViewCost) {
            $handle = fopen('php://output', 'w');
            $columns = ['Code', 'Name', 'Group'];
            if ($canViewCost) {
                $columns[] = 'Cost Price';
            }
            $columns[] = 'Sale Price';
            $columns[] = 'Quantity';
            fputcsv($handle, $columns);

            foreach ($products as $p) {
                $row = [$p->product_code, $p->product_name, $p->brand];
                if ($canViewCost) {
                    $row[] = $p->cost_price;
                }
                $row[] = $p->price;
                $row[] = $p->quantity;
                fputcsv($handle, array_map([\App\Support\CsvCell::class, 'safe'], $row));
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
    //EDIT PRODUCT
    public function editProduct($id)
    {
        $prod = Products::findOrFail($id);
        return view('products.edit_product', compact('prod'));
    }
    //DELETE PRODUCT
    public function deleteProduct(Request $request)
    {
        $prod_id = $request->input('delete_product');

        $prod = Products::findOrFail($prod_id);
        if ((\Illuminate\Support\Facades\Schema::hasTable('workshop_components') && DB::table('workshop_components')->where('product_id',$prod_id)->exists()) || (\Illuminate\Support\Facades\Schema::hasTable('workshop_production') && DB::table('workshop_production')->where('product_id',$prod_id)->exists()) || DB::table('hub_stock')->where('product_id',$prod_id)->exists() || DB::table('hub_stock_events')->where('product_id',$prod_id)->exists() || DB::table('hub_deliveries')->where('product_id',$prod_id)->exists() || DB::table('stock_transfers')->where('product_id', $prod_id)->exists() || DB::table('order_details')->where('product_id', $prod_id)->exists() || DB::table('stock_ins')->where('product_id', $prod_id)->exists()) {
            return redirect()->back()->with('error', 'This product has sales or stock history and cannot be deleted.');
        }
        $prod->delete();

        return redirect()->back()->with('success','Product Deleted Successfully');
    }
    public function updatePrice(Request $request,int $id)
    {
        abort_unless($request->user()->active && $request->user()->hasAccess('edit_prices'),403);
        $data=$request->validate(['price'=>'required|integer|min:0|max:2147483647','expected_price'=>'required|integer|min:0|max:2147483647']);
        $price=DB::transaction(function()use($id,$data){
            $product=DB::table('products')->where('id',$id)->lockForUpdate()->first();abort_unless($product,404);
            if((int)$product->price!==(int)$data['expected_price'])throw \Illuminate\Validation\ValidationException::withMessages(['price'=>'This price has changed since the list was loaded. Refresh the list and check it before saving.']);
            DB::table('products')->where('id',$id)->update(['price'=>(int)$data['price'],'updated_at'=>now()]);
            return (int)$data['price'];
        });
        return response()->json(['product_id'=>$id,'price'=>$price,'message'=>'Selling price updated.'])->header('Cache-Control','private, no-store');
    }

    //UPDATE PRODUCT
    public function updateProduct(Request $request, $id)
    {
        $product = Products::findOrFail($id);
        $request->validate([
            'product_name' => 'required|max:255',
            'product_code' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products', 'product_code')->ignore($product)],
            'brand' => 'required',
            'price' => 'required|numeric|min:0',
        ]);

        Products::findOrFail($id);

        // Stock quantity is intentionally not editable here — it's per-shop
        // now, and corrections go through Stock-In so there's always an
        // auditable reason/reference instead of a silent overwrite.
        DB::table('products')->where('id', $id)->update([
            'product_name' => $request->product_name,
            'product_code' => $request->product_code,
            'brand' => $request->brand,
            'price' => $request->price,
        ]);

        return redirect()->route('list_product')->with('success', 'Product Updated Successfully');
    }
}
