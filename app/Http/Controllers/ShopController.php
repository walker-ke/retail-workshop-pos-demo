<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $shops = Shop::orderBy('name')->get();
        return view('shops.shop_list', compact('shops'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('shops')->with('error', $validator->errors()->first());
        }

        Shop::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('shops')->with('success', 'Shop Added Successfully');
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('shops')->with('error', $validator->errors()->first());
        }

        \App\Support\StockTransactions::run(function() use($request,$id){
            $shop=Shop::where('id',$id)->lockForUpdate()->firstOrFail();
            if(!$request->has('is_active')) {
                $open=\Illuminate\Support\Facades\DB::table('stock_transfers')->whereIn('status',['requested','accepted','dispatched'])->where(fn($q)=>$q->where('from_shop_id',$id)->orWhere('to_shop_id',$id))->exists();
                $deliveries=\Illuminate\Support\Facades\DB::table('hub_deliveries')->where('shop_id',$id)->whereIn('status',['dispatched','reported'])->exists();
                if($open || $deliveries) throw \Illuminate\Validation\ValidationException::withMessages(['shop'=>'Finish or cancel this shop’s open transfers and hub deliveries before making it inactive.']);
            }
            $shop->name=$request->name;$shop->address=$request->address;$shop->phone=$request->phone;$shop->is_active=$request->has('is_active');$shop->save();
        });

        return redirect()->route('shops')->with('success', 'Shop Updated Successfully');
    }

    public function destroy(Request $request)
    {
        $shop_id = $request->input('delete_shop');

        if (Shop::count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the only remaining shop.');
        }

        try {
            Shop::find($shop_id)?->delete();
        } catch (QueryException $e) {
            return redirect()->back()->with('error', 'This shop has sales, stock, or staff tied to it and cannot be deleted. Mark it inactive instead.');
        }

        return redirect()->back()->with('success', 'Shop Deleted Successfully');
    }
}
