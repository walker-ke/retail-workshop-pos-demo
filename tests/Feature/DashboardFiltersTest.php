<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class DashboardFiltersTest extends TestCase
{
    public function test_invalid_dates_are_rejected_before_querying_sales(): void
    {
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->get('/home?from=not-a-date')->assertRedirect()->assertSessionHasErrors('from');
        $this->get('/home?from=2026-10-10&to=2026-10-01')->assertRedirect()->assertSessionHasErrors('to');
        $this->get('/home?week=2026-99-99')->assertRedirect()->assertSessionHasErrors('week');
    }
}
