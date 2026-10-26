<?php
namespace App\Support;

use Carbon\Carbon;

class ReceiptData
{
    public static function business($shop): array
    {
        $data = [];
        foreach (['name', 'pin', 'phone', 'address', 'paybill', 'account_number', 'till_number', 'footer'] as $field) {
            $data[$field] = $shop->{'receipt_'.$field} ?? '';
        }
        $data['name'] = $data['name'] ?: 'DEMO RETAIL WORKSHOP';
        $data['footer'] = $data['footer'] ?: 'THANK YOU FOR SHOPPING WITH US';
        $data['till_number'] = $data['till_number'] ?: str_pad((string) ($shop->id ?? ''), 2, '0', STR_PAD_LEFT);
        return $data;
    }

    public static function customerName($name): string
    {
        return trim((string) $name) ?: 'Walk-in Customer';
    }

    public static function amount($value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    public static function preview($shop, $customer, $contents, $method, $cashier, $total): array
    {
        return [
            'business' => self::business($shop), 'is_preview' => true, 'title' => 'SALE PREVIEW',
            'customer' => $customer->customer_name, 'date' => now()->timezone('Africa/Nairobi')->format('d-M-Y g:i a'),
            'number' => null, 'payment_method' => $method === '__combined' ? 'Combined payment' : ($method ?? ''), 'served_by' => $cashier->name,
            'items' => $contents->map(fn ($item) => ['name' => $item->name, 'code' => $item->options->code ?? '', 'quantity' => $item->qty, 'price' => $item->price, 'total' => $item->price * $item->qty])->values()->all(),
            'total_items' => $contents->sum('qty'), 'total' => self::amount($total), 'paid' => null, 'change' => null,
        ];
    }

    public static function order($order, $details, $shop): array
    {
        $settings = $order->receipt_settings ?? null;
        if (is_string($settings)) $settings = json_decode($settings, true);
        $total = self::amount($order->total);
        $paid = self::amount($order->paid_amount);
        $method = $order->payment_method ?? '';
        $payments = $order->payment_breakdown ?? null;
        if (is_string($payments)) $payments = json_decode($payments, true);
        return [
            'business' => is_array($settings) ? $settings : self::business($shop),
            'is_preview' => false, 'title' => strcasecmp($method, 'cash') === 0 ? 'CASH SALE' : 'SALES RECEIPT',
            'customer' => $order->customer_name ?: 'Walk-in Customer',
            'date' => isset($order->created_at) ? Carbon::parse($order->created_at, config('app.timezone'))->timezone('Africa/Nairobi')->format('d-M-Y g:i a') : $order->order_date,
            'number' => $order->transaction_code, 'payment_method' => $method,
            'served_by' => $order->cashier_name ?: 'Not recorded (older sale)',
            'items' => $details->map(fn ($item) => ['name' => $item->product_name ?? 'Product unavailable', 'code' => $item->product_code ?? '', 'quantity' => $item->quantity, 'price' => self::amount($item->unit_price), 'total' => self::amount($item->total)])->values()->all(),
            'payments' => is_array($payments) ? $payments : [],
            'total_items' => $details->sum('quantity'), 'total' => $total, 'paid' => $paid, 'change' => max(0, $paid - $total),
        ];
    }
}
