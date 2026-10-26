<?php
namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransactions
{
    public static function run(callable $operation)
    {
        // Retry the complete rolled-back transaction, with at most five attempts.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try { return DB::transaction($operation); }
            catch (QueryException $error) {
                $code = (int) ($error->errorInfo[1] ?? 0);
                if (!in_array($code, [1020, 1205, 1213], true) && !($code === 1062 && str_contains($error->getMessage(), 'orders_transaction_code_unique'))) throw $error;
                if ($attempt === 4) throw ValidationException::withMessages(['transaction' => 'The transaction could not complete safely. Please try again.']);
                usleep(20000 * ($attempt + 1));
            }
        }
    }

    public static function lock(array $productIds, int $shopId): void
    {
        sort($productIds, SORT_NUMERIC);
        foreach (array_unique($productIds) as $productId) {
            DB::table('product_stock')->insertOrIgnore(['product_id' => $productId, 'shop_id' => $shopId, 'quantity' => 0]);
            $stock = DB::table('product_stock')->where('product_id', $productId)->where('shop_id', $shopId)->lockForUpdate()->first();
            if (!$stock) throw ValidationException::withMessages(['transaction' => 'This product is no longer available. Remove it from the cart and try again.']);
        }
    }
}
