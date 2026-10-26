<?php

namespace App\Support;

class DailyPayments
{
    // Integer cents avoid rounding drift across many sales.
    private static function cents($value): int
    {
        return max(0, (int) round((float) str_replace(',', '', (string) $value) * 100));
    }

    public static function summary(iterable $orders): array
    {
        $totals = [];
        $hourly = array_fill(0, 24, []);
        $count = $pending = 0;
        foreach ($orders as $order) {
            $count++;
            if (strcasecmp($order->order_status ?? '', 'Pending') === 0) $pending++;
            $hour = (int) \Carbon\CarbonImmutable::parse($order->created_at, config('app.timezone'))->setTimezone('Africa/Nairobi')->format('G');
            foreach (self::totals([$order]) as $method => $amount) {
                $totals[$method] = ($totals[$method] ?? 0) + $amount;
                $hourly[$hour][$method] = ($hourly[$hour][$method] ?? 0) + $amount;
            }
        }
        arsort($totals);
        return compact('totals', 'hourly', 'count', 'pending');
    }

    public static function totals(iterable $orders): array
    {
        $totals = [];
        foreach ($orders as $order) {
            $total = self::cents($order->total);
            $collected = min($total, self::cents($order->paid_amount));
            if ($collected === 0) continue;
            $parts = json_decode($order->payment_breakdown ?? 'null', true);
            $valid = is_array($parts) && count($parts) > 0;
            $split = [];
            foreach ($valid ? $parts : [] as $part) {
                if (!is_array($part) || !is_string($part['method'] ?? null) || trim($part['method']) === '' || !is_numeric($part['amount'] ?? null)) {
                    $valid = false;
                    break;
                }
                $method = trim($part['method']);
                $split[$method] = ($split[$method] ?? 0) + self::cents($part['amount']);
            }
            // Never silently drop a legacy or malformed split payment.
            if (!$valid || array_sum($split) !== $collected) {
                $method = trim($order->payment_method ?? '') ?: 'Not recorded';
                $split = [$method => $collected];
            }
            foreach ($split as $method => $amount) {
                $totals[$method] = ($totals[$method] ?? 0) + $amount;
            }
        }
        arsort($totals);
        return $totals;
    }
}
