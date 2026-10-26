<?php
namespace Tests\Feature;
use App\Support\HistoryFilters;
use Illuminate\Database\MySqlConnection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class HistoryFiltersTest extends TestCase
{
    private function query(array $input)
    {
        $this->app->instance('request', Request::create('/transaction-history', 'GET', $input));
        return (new MySqlConnection(null, 'test'))->table('orders')->where('orders.shop_id', 7);
    }
    public function test_search_alternatives_cannot_escape_the_shop_boundary(): void
    {
        $query = $this->query(['q'=>'old receipt']);
        $this->assertSame(50, HistoryFilters::apply($query, 'orders', ['transaction_code','customer_name']));
        $this->assertSame('select * from `orders` where `orders`.`shop_id` = ? and (`orders`.`transaction_code` like ? or `orders`.`customer_name` like ?)', $query->toSql());
        $this->assertSame([7,'%old receipt%','%old receipt%'], $query->getBindings());
    }
    public function test_unbounded_page_sizes_are_rejected(): void
    {
        $query = $this->query(['per_page'=>1000000]);
        $this->expectException(ValidationException::class);
        HistoryFilters::apply($query, 'orders', ['transaction_code']);
    }
}
