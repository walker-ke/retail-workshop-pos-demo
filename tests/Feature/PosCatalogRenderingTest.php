<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PosCatalogRenderingTest extends TestCase
{
    public function test_catalog_is_encoded_safely_without_thousands_of_hidden_forms(): void
    {
        $user = new class(['name' => 'Performance test', 'access_level' => 1]) extends User {
            public function isTopAdmin(): bool { return false; }
        };
        $this->actingAs($user);
        \Cart::shouldReceive('content')->andReturn(collect());
        foreach (['subtotal', 'tax', 'total'] as $method) {
            \Cart::shouldReceive($method)->andReturn('0.00');
        }
        $name = '</script><img src=x onerror=alert(1)> & "quoted"';
        $catalog = collect(range(1, 2910))->map(function ($id) use ($name) {
            return ['id' => $id, 'name' => $name, 'code' => (string) $id, 'brand' => null, 'price' => '450.50', 'quantity' => 7];
        });
        $html = view('pos.pos_index', [
            'catalog' => $catalog, 'customer' => collect(), 'categories' => collect(),
            'paymentTypes' => collect(), 'shopId' => 1, 'errors' => new ViewErrorBag(),
        ])->render();
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringNotContainsString('class="add-cart-form"', $html);
        $this->assertStringNotContainsString('jquery.dataTables.min.js', $html);
        $this->assertStringContainsString('pos-catalog.js', $html);
        preg_match('/<script type="application\/json" id="posCatalog"[^>]*>(.*?)<\/script>/s', $html, $matches);
        $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2910, $decoded);
        $this->assertSame($name, $decoded[2909]['name']);
        $this->assertSame('450.50', $decoded[2909]['price']);
    }
}
