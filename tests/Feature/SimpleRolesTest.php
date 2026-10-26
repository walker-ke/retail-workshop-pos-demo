<?php
namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureAccessLevel;
use Illuminate\Http\Request;
use Tests\TestCase;

class SimpleRolesTest extends TestCase
{
    public function test_roles_have_fixed_permissions_without_database_thresholds(): void
    {
        $sales = ['dashboard', 'pos', 'void_item', 'void_order', 'refund', 'view_sales_history', 'zero_stock_quantity_sale'];
        foreach ([User::CASHIER, User::MANAGER, User::OWNER] as $role) {
            $user = new User(['access_level' => $role]);
            foreach ($sales as $operation) $this->assertTrue($user->hasAccess($operation));
            $this->assertSame($role !== User::CASHIER, $user->hasAccess('stock'));
            foreach (['users_security', 'payment_types', 'backup', 'products', 'shops', 'reporting', 'customers_suppliers', 'unrecognized_operation'] as $operation) {
                $this->assertSame($role === User::OWNER, $user->hasAccess($operation));
            }
            $this->assertSame($role === User::OWNER, $user->isTopAdmin());
        }
        $this->assertFalse((new User(['access_level' => 5]))->hasAccess('pos'));
    }

    public function test_cashiers_and_managers_cannot_change_users_or_security_directly(): void
    {
        foreach ([User::CASHIER, User::MANAGER] as $role) {
            $this->actingAs(new User(['name' => 'Role test', 'access_level' => $role, 'shop_id' => 1]));
            $this->get('/user-list')->assertRedirect(route('pos'));
            $this->get('/user-security')->assertRedirect(route('pos'));
            $this->post('/add-user', ['access_level' => User::OWNER])->assertRedirect(route('pos'));
            $this->post('/update-user/1', ['access_level' => User::OWNER])->assertRedirect(route('pos'));
            $this->post('/reset-password/1', ['password' => 'attempt'])->assertRedirect(route('pos'));
            $this->delete('/delete-user', ['delete_user' => 1])->assertRedirect(route('pos'));
            $this->post('/receipt-settings/1', ['receipt_name' => 'Forged'])->assertForbidden();
        }
    }

    public function test_management_routes_cannot_be_called_by_cashiers_or_managers(): void
    {
        foreach ([User::CASHIER, User::MANAGER] as $role) {
            $this->actingAs(new User(['access_level' => $role, 'shop_id' => 1]));
            foreach (['/add-product','/add-brand','/update-product/1','/import-products','/add-shop','/update-shop/1','/add-payment-type','/update-payment-type/1'] as $url) {
                $this->post($url)->assertRedirect(route('pos'));
            }
            foreach (['/delete-product','/delete-brand','/delete-shop','/delete-payment-type'] as $url) {
                $this->delete($url)->assertRedirect(route('pos'));
            }
            $this->put('/edit-brand')->assertRedirect(route('pos'));
        }
    }

    public function test_stock_adjustment_is_available_to_manager_but_not_cashier(): void
    {
        $this->actingAs(new User(['access_level' => User::CASHIER, 'shop_id' => 1]));
        $this->post('/quick-stock-update', ['product_id' => 1, 'quantity' => 100])->assertRedirect(route('pos'));
        $this->post('/add-stock', ['product_id' => 1, 'quantity' => 100])->assertRedirect(route('pos'));
        $this->actingAs(new User(['access_level' => User::MANAGER, 'shop_id' => 1]));
        $response = (new EnsureAccessLevel())->handle(Request::create('/quick-stock-update', 'POST'), fn () => response('Manager allowed'), 'stock');
        $this->assertSame('Manager allowed', $response->getContent());
    }

    public function test_owner_security_screen_has_three_roles_and_no_threshold_editor(): void
    {
        // Keep this presentation check independent of the database-backed shop switcher.
        $user = new class(['access_level' => User::OWNER, 'name' => 'Owner']) extends User {
            public function isTopAdmin(): bool { return false; }
            public function hasAccess(string $operationKey): bool { return true; }
        };
        $this->actingAs($user);
        $this->get('/user-security')->assertOk()->assertSee('Cashier')->assertSee('Manager')->assertSee('Owner / Admin')->assertDontSee('Set access level for predefined operations')->assertDontSee('stepper-input');
    }
    public function test_navigation_shows_only_the_destinations_allowed_for_each_role(): void
    {
        $destinations = [
            'home' => 'dashboard', 'pos' => 'pos', 'list_product' => 'view_products',
            'hubs.index' => 'hubs', 'workshop.index'=>'workshop',
            'stock_index' => 'stock', 'shops' => 'shops', 'transaction' => 'view_sales_history',
            'refunds' => 'refund', 'report_profit' => 'reporting', 'user' => 'users_security',
            'user_security' => 'users_security', 'payment_types' => 'payment_types',
            'backup' => 'backup', 'receipt_settings' => 'users_security',
        ];
        foreach (User::roles() as $role => $label) {
            $user = new User(['access_level' => $role, 'name' => $label, 'shop_id' => 1]);
            $this->actingAs($user);
            $html = view('layouts.includes.sidebar')->render();
            foreach ($destinations as $route => $operation) {
                $link = 'href="'.route($route).'"';
                if ($user->hasAccess($operation)) $this->assertStringContainsString($link, $html);
                else $this->assertStringNotContainsString($link, $html);
            }
            $this->assertStringNotContainsString('Employees', $html);
            $this->assertStringNotContainsString('About', $html);
            $this->assertStringNotContainsString('List of Customers', $html);
        }
    }

}
