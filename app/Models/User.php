<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'access_level',
        'shop_id',
        'active',
    ];

    public const CASHIER = 0;
    public const MANAGER = 1;
    public const OWNER = 9;

    public static function roles(): array
    {
        return [self::CASHIER => 'Cashier', self::MANAGER => 'Manager', self::OWNER => 'Owner / Admin'];
    }

    public function roleLabel(): string
    {
        return self::roles()[(int) $this->access_level] ?? 'Unassigned';
    }

    public function isTopAdmin(): bool
    {
        return (int) $this->access_level === self::OWNER;
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Cashiers/managers always use their assigned shop. Owners can override
     * their default shop with the selector and see "all shops" on
     * read-only screens (dashboard/reports) until they explicitly switch
     * into one via the navbar shop-switcher, which stores the choice in
     * the session rather than on the user row. Returns null for "all shops".
     */
    public function activeShopId(): ?int
    {
        if ($this->isTopAdmin() && session()->exists('current_shop_id')) {
            return session('current_shop_id') ?: null;
        }
        return $this->shop_id !== null ? (int) $this->shop_id : null;
    }

    public function hasAccess(string $operationKey): bool
    {
        if (config('demo.enabled') && in_array($operationKey, ['backup', 'users_security', 'shops', 'payment_types'], true)) return false;
        if ($this->isTopAdmin()) return true;
        if (!in_array((int) $this->access_level, [self::CASHIER, self::MANAGER], true)) return false;
        $sales = ['stock_transfers', 'dashboard', 'pos', 'void_order', 'void_item', 'refund', 'view_sales_history', 'zero_stock_quantity_sale'];
        if (in_array($operationKey, $sales, true)) return true;
        return (int) $this->access_level === self::MANAGER
            && in_array($operationKey, ['stock', 'quick_inventory', 'view_cost_prices', 'view_products', 'edit_prices', 'hubs', 'workshop'], true);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];
}
