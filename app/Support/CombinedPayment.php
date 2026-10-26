<?php
namespace App\Support;

use Illuminate\Validation\ValidationException;

class CombinedPayment
{
    private static function cents($value): int
    {
        $value = str_replace(',', '', (string) $value);
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['payment' => 'Use positive amounts with at most two decimal places.']);
        }
        $parts = explode('.', $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function tender($total, $paid, bool $changeAllowed, bool $markPaid): float
    {
        $totalCents = self::cents($total);
        $paidCents = self::cents($paid);
        if (($changeAllowed || $markPaid) && $paidCents < $totalCents) {
            throw ValidationException::withMessages(['paid_amount' => 'The amount received must cover the total.']);
        }
        if (!$changeAllowed && $paidCents > $totalCents) {
            throw ValidationException::withMessages(['paid_amount' => 'This payment method requires the exact total; change is not allowed.']);
        }
        return $paidCents / 100;
    }

    public static function breakdown($total, $cash, $paybill): array
    {
        $cashCents = self::cents($cash);
        $paybillCents = self::cents($paybill);
        if ($cashCents <= 0 || $paybillCents <= 0 || $cashCents + $paybillCents !== self::cents($total)) {
            throw ValidationException::withMessages(['payment' => 'Cash and Paybill must both be greater than zero and add up to the total.']);
        }
        return [['method' => 'Cash', 'amount' => $cashCents / 100], ['method' => 'Paybill', 'amount' => $paybillCents / 100]];
    }
}
