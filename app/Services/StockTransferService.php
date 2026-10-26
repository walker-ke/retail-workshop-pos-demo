<?php
namespace App\Services;

use App\Models\User;
use App\Support\StockTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function change(int $id, string $action, User $actor, ?int $quantity = null, ?string $note = null): void
    {
        StockTransactions::run(function () use ($id, $action, $actor, $quantity, $note) {
            $transfer = DB::table('stock_transfers')->where('id', $id)->lockForUpdate()->first();
            abort_unless($transfer, 404);
            $source = $actor->isTopAdmin() || $actor->activeShopId() === (int) $transfer->from_shop_id;
            $destination = $actor->isTopAdmin() || $actor->activeShopId() === (int) $transfer->to_shop_id;
            abort_unless(($action === 'receive' && $destination) || (in_array($action, ['approve', 'accept', 'decline', 'dispatch'], true) && $source) || ($action === 'cancel' && $destination), 403);
            $expected = ['approve'=>'requested', 'accept'=>'requested', 'decline'=>'requested', 'dispatch'=>'accepted', 'receive'=>'dispatched'];
            if ($action === 'cancel') {
                $valid = in_array($transfer->status, ['requested', 'accepted'], true);
            } else $valid = isset($expected[$action]) && $transfer->status === $expected[$action];
            if (!$valid) throw ValidationException::withMessages(['transfer'=>'This request has already changed. Refresh to see its current status.']);
            $shops = [(int) $transfer->from_shop_id, (int) $transfer->to_shop_id]; sort($shops, SORT_NUMERIC);
            foreach ($shops as $shopId) StockTransactions::lock([(int) $transfer->product_id], $shopId);
            $stock = DB::table('product_stock')->where('shop_id', $transfer->from_shop_id)->where('product_id', $transfer->product_id)->first();
            $amount = (int) $transfer->accepted_quantity;
            $update = ['updated_at'=>now()];
            if ($action === 'approve') {
                if ($quantity === null || $quantity < 1 || $quantity > $transfer->requested_quantity || $quantity > $stock->quantity - $stock->reserved_quantity) {
                    throw ValidationException::withMessages(['quantity'=>'Confirm a quantity no greater than requested and currently available.']);
                }
                if (DB::table('shops')->whereIn('id', $shops)->where('is_active', true)->count() !== 2) throw ValidationException::withMessages(['transfer'=>'Both shops must be active.']);
                $receivingStock = DB::table('product_stock')->where('shop_id', $transfer->to_shop_id)->where('product_id', $transfer->product_id)->first();
                if ((int) $receivingStock->quantity > 5) throw ValidationException::withMessages(['stock'=>'The receiving shop already has more than 5 of this item. Decline this request.']);
                DB::table('product_stock')->where('shop_id', $transfer->from_shop_id)->where('product_id', $transfer->product_id)->decrement('quantity', $quantity);
                $this->stockHistory($transfer, (int) $transfer->from_shop_id, -$quantity, 'Confirmed');
                $update += ['status'=>'dispatched', 'accepted_quantity'=>$quantity]; $amount = $quantity;
            } elseif ($action === 'accept') {
                if ($quantity === null || $quantity < 1 || $quantity > $transfer->requested_quantity || $quantity > $stock->quantity - $stock->reserved_quantity) {
                    throw ValidationException::withMessages(['quantity'=>'Accept a quantity no greater than requested and currently available.']);
                }
                if (DB::table('shops')->whereIn('id', $shops)->where('is_active', true)->count() !== 2) throw ValidationException::withMessages(['transfer'=>'Both shops must be active.']);
                DB::table('product_stock')->where('shop_id', $transfer->from_shop_id)->where('product_id', $transfer->product_id)->increment('reserved_quantity', $quantity);
                $update += ['status'=>'accepted', 'accepted_quantity'=>$quantity]; $amount = $quantity;
            } elseif ($action === 'dispatch') {
                if ($stock->reserved_quantity < $amount || $stock->quantity < $amount) throw ValidationException::withMessages(['transfer'=>'Reserved stock is no longer available. Contact the owner.']);
                DB::table('product_stock')->where('shop_id', $transfer->from_shop_id)->where('product_id', $transfer->product_id)->update(['quantity'=>$stock->quantity-$amount, 'reserved_quantity'=>$stock->reserved_quantity-$amount]);
                $this->stockHistory($transfer, (int) $transfer->from_shop_id, -$amount, 'Dispatched');
                $update['status'] = 'dispatched';
            } elseif ($action === 'receive') {
                DB::table('product_stock')->where('shop_id', $transfer->to_shop_id)->where('product_id', $transfer->product_id)->increment('quantity', $amount);
                $this->stockHistory($transfer, (int) $transfer->to_shop_id, $amount, 'Received');
                $update['status'] = 'received';
            } else {
                if ($transfer->status === 'accepted') {
                    if ($stock->reserved_quantity < $amount) throw ValidationException::withMessages(['transfer'=>'Reservation mismatch. Contact the owner.']);
                    DB::table('product_stock')->where('shop_id', $transfer->from_shop_id)->where('product_id', $transfer->product_id)->decrement('reserved_quantity', $amount);
                }
                $update['status'] = $action === 'decline' ? 'declined' : 'cancelled';
            }
            DB::table('stock_transfers')->where('id', $id)->update($update);
            $this->event($id, $action, $actor, $amount ?: null, $note);
        });
    }

    public function event(int $id, string $action, User $actor, ?int $quantity, ?string $note): void
    {
        DB::table('stock_transfer_events')->insert(['stock_transfer_id'=>$id, 'action'=>$action, 'user_id'=>$actor->id, 'actor_name'=>$actor->name, 'quantity'=>$quantity, 'note'=>$note, 'created_at'=>now()]);
    }

    private function stockHistory(object $transfer, int $shopId, int $delta, string $action): void
    {
        DB::table('stock_ins')->insert(['shop_id'=>$shopId, 'product_id'=>$transfer->product_id, 'quantity'=>$delta, 'cost_price'=>0, 'reference'=>'Transfer #'.$transfer->id, 'notes'=>$action.' shop transfer', 'created_at'=>now(), 'updated_at'=>now()]);
    }
}
