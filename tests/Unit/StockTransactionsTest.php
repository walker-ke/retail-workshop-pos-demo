<?php
namespace Tests\Unit;
use App\Support\StockTransactions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class StockTransactionsTest extends TestCase
{
    private function conflict(int $code): QueryException
    {
        $cause = new \PDOException('Database conflict');
        $cause->errorInfo = ['HY000', $code, 'Database conflict'];
        return new QueryException('mysql', 'UPDATE product_stock', [], $cause);
    }
    public function test_transient_mariadb_stock_conflict_is_retried(): void
    {
        DB::shouldReceive('transaction')->once()->andThrow($this->conflict(1020));
        DB::shouldReceive('transaction')->once()->andReturn(42);
        $this->assertSame(42, StockTransactions::run(fn () => 42));
    }
    public function test_exhausted_stock_conflicts_return_validation_instead_of_server_error(): void
    {
        DB::shouldReceive('transaction')->times(5)->andThrow($this->conflict(1020));
        $this->expectException(ValidationException::class);
        StockTransactions::run(fn () => null);
    }
    public function test_receipt_number_collision_is_retried(): void
    {
        $cause = new \PDOException("Duplicate entry for key 'orders_transaction_code_unique'");
        $cause->errorInfo = ['23000', 1062, $cause->getMessage()];
        DB::shouldReceive('transaction')->once()->andThrow(new QueryException('mysql', 'INSERT orders', [], $cause));
        DB::shouldReceive('transaction')->once()->andReturn(43);
        $this->assertSame(43, StockTransactions::run(fn () => 43));
    }
    public function test_unrelated_database_errors_are_not_hidden_or_retried(): void
    {
        DB::shouldReceive('transaction')->once()->andThrow($this->conflict(1146));
        $this->expectException(QueryException::class);
        StockTransactions::run(fn () => null);
    }
}
