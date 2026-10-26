<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class PaymentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $paymentTypes = PaymentType::orderBy('position')->orderBy('name')->get();
        return view('payment_types.payment_type_list', compact('paymentTypes'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'position' => 'nullable|integer|min:0',
            'code' => 'nullable|max:50',
        ]);

        if ($validator->fails()) {
            return redirect()->route('payment_types')->with('error', $validator->errors()->first());
        }

        PaymentType::create([
            'name' => $request->name,
            'position' => $request->position ?: 0,
            'code' => $request->code,
            'enabled' => $request->has('enabled'),
            'quick_payment' => $request->has('quick_payment'),
            'customer_required' => $request->has('customer_required'),
            'change_allowed' => $request->has('change_allowed'),
            'mark_transaction_as_paid' => $request->has('mark_transaction_as_paid'),
            'print_receipt' => $request->has('print_receipt'),
        ]);

        return redirect()->route('payment_types')->with('success', 'Payment Type Added Successfully');
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'position' => 'nullable|integer|min:0',
            'code' => 'nullable|max:50',
        ]);

        if ($validator->fails()) {
            return redirect()->route('payment_types')->with('error', $validator->errors()->first());
        }

        $paymentType = PaymentType::findOrFail($id);
        $paymentType->name = $request->name;
        $paymentType->position = $request->position ?: 0;
        $paymentType->code = $request->code;
        $paymentType->enabled = $request->has('enabled');
        $paymentType->quick_payment = $request->has('quick_payment');
        $paymentType->customer_required = $request->has('customer_required');
        $paymentType->change_allowed = $request->has('change_allowed');
        $paymentType->mark_transaction_as_paid = $request->has('mark_transaction_as_paid');
        $paymentType->print_receipt = $request->has('print_receipt');
        $paymentType->save();

        return redirect()->route('payment_types')->with('success', 'Payment Type Updated Successfully');
    }

    public function destroy(Request $request)
    {
        $id = $request->input('delete_payment_type');

        if (PaymentType::count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the only remaining payment type.');
        }

        try {
            PaymentType::find($id)?->delete();
        } catch (QueryException $e) {
            return redirect()->back()->with('error', 'This payment type could not be deleted.');
        }

        return redirect()->back()->with('success', 'Payment Type Deleted Successfully');
    }
}
