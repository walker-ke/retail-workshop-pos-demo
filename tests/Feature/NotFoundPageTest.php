<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class NotFoundPageTest extends TestCase
{
    public function test_missing_links_show_custom_page_for_guests_and_logged_in_users(): void
    {
        $this->get('/page-that-does-not-exist')->assertNotFound()->assertSee('Page not found')->assertSee('Go to POS')->assertSee('Sign in');
        $this->actingAs(new User(['access_level' => User::CASHIER, 'name' => 'Jan', 'shop_id' => 2]));
        $this->get('/list-customer')->assertNotFound()->assertSee('DEMO RETAIL WORKSHOP')->assertSee('Go to POS');
    }
}
