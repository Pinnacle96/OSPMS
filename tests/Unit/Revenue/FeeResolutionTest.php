<?php

namespace Tests\Unit\Revenue;

use App\Domains\Geography\Models\Lga;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Revenue\Services\ResolveApplicableFeeService;
use App\Domains\Routes\Models\Route;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\FoundationTestCase;

class FeeResolutionTest extends FoundationTestCase
{
    private function context(): array
    {
        $head = RevenueHead::create(['code' => 'DPT', 'name' => 'Synthetic daily fee', 'frequency' => 'daily', 'status' => 'active']);
        $lga = Lga::create(['code' => 'A', 'name' => 'Synthetic A', 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => 'A', 'name' => 'Synthetic park', 'address' => 'Synthetic', 'status' => 'active']);
        $route = Route::create(['route_code' => 'A', 'origin' => 'Demo A', 'destination' => 'Demo B', 'status' => 'active']);

        return [$head, $lga, $park, $route];
    }

    private function fee(RevenueHead $head, array $extra = []): FeeConfiguration
    {
        return FeeConfiguration::create(array_replace(['revenue_head_id' => $head->id, 'amount' => '500.00', 'currency' => 'NGN', 'priority' => 0, 'effective_from' => '2026-01-01 00:00:00', 'status' => 'active'], $extra));
    }

    public function test_all_seven_recommended_scope_levels_resolve_in_order_before_numeric_priority(): void
    {
        [$head,$lga,$park,$route] = $this->context();
        $service = app(ResolveApplicableFeeService::class);
        $at = CarbonImmutable::parse('2026-10-07 10:00:00');
        $levels = [[], ['vehicle_type' => 'bus'], ['lga_id' => $lga->id], ['lga_id' => $lga->id, 'vehicle_type' => 'bus'], ['park_id' => $park->id], ['park_id' => $park->id, 'vehicle_type' => 'bus'], ['park_id' => $park->id, 'route_id' => $route->id, 'vehicle_type' => 'bus']];
        foreach ($levels as $i => $scope) {
            $fee = $this->fee($head, $scope + ['amount' => (string) (500 + $i), 'priority' => 1000 - $i]);
            $this->assertSame($fee->id, $service->resolve($head, 'bus', $park, $route->id, $at)->id);
        }
    }

    public function test_route_only_and_combined_scope_all_constraints_match(): void
    {
        [$head,$lga,$park,$route] = $this->context();
        $default = $this->fee($head);
        $r = $this->fee($head, ['route_id' => $route->id]);
        $svc = app(ResolveApplicableFeeService::class);
        $at = CarbonImmutable::parse('2026-10-07');
        $this->assertSame($r->id, $svc->resolve($head, 'bus', $park, $route->id, $at)->id);
        $this->assertSame($default->id, $svc->resolve($head, 'bus', $park, null, $at)->id);
        $wrong = Lga::create(['code' => 'B', 'name' => 'Synthetic B', 'status' => 'active']);
        $this->fee($head, ['park_id' => $park->id, 'lga_id' => $wrong->id, 'priority' => 9999]);
        $this->assertSame($r->id, $svc->resolve($head, 'bus', $park, $route->id, $at)->id);
    }

    public function test_effective_start_is_inclusive_end_is_exclusive_and_inactive_future_expired_are_ignored(): void
    {
        [$head,$lga,$park,$route] = $this->context();
        $at = CarbonImmutable::parse('2026-10-07 10:00:00');
        $good = $this->fee($head, ['effective_from' => $at]);
        foreach ([['status' => 'inactive'], ['status' => 'draft'], ['status' => 'expired'], ['effective_from' => $at->addSecond()], ['effective_to' => $at]] as $bad) {
            $this->fee($head, $bad + ['park_id' => $park->id]);
        }
        $this->assertSame($good->id, app(ResolveApplicableFeeService::class)->resolve($head, 'bus', $park, null, $at)->id);
    }

    public function test_priority_then_latest_effective_date_breaks_equally_specific_matches(): void
    {
        [$head,$lga,$park] = $this->context();
        $this->fee($head, ['priority' => 1]);
        $this->fee($head, ['priority' => 3]);
        $best = $this->fee($head, ['priority' => 3, 'effective_from' => '2026-02-01']);
        $this->assertSame($best->id, app(ResolveApplicableFeeService::class)->resolve($head, 'bus', $park, null, CarbonImmutable::parse('2026-10-07'))->id);
    }

    public function test_equal_precedence_is_rejected_instead_of_silently_choosing_a_fee(): void
    {
        [$head,$lga,$park] = $this->context();
        $this->fee($head);
        $this->fee($head);
        $this->expectException(ValidationException::class);
        app(ResolveApplicableFeeService::class)->resolve($head, 'bus', $park, null, CarbonImmutable::parse('2026-10-07'));
    }

    public function test_no_match_and_inactive_head_are_explicit_errors(): void
    {
        [$head,$lga,$park] = $this->context();
        foreach ([false, true] as $inactive) {
            if ($inactive) {
                $this->fee($head);
                $head->update(['status' => 'inactive']);
            }
            try {
                app(ResolveApplicableFeeService::class)->resolve($head, 'bus', $park);
                $this->fail('Expected no applicable fee.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_decimal_normalization_preserves_maximum_precision_without_float(): void
    {
        $this->assertSame('9999999999999.99', Money::normalize('9999999999999.99'));
        $this->assertSame('0.10', Money::normalize('0.1'));
        $this->assertSame('500.00', Money::normalize('500'));
        $this->expectException(ValidationException::class);
        Money::normalize('500.001');
    }
}
