<?php

namespace Tests\Feature\Ticketing;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Actions\CancelTicketAction;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Domains\Ticketing\Services\TicketReferenceService;
use App\Domains\Ticketing\Services\TicketVerificationService;
use App\Domains\Vehicles\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TicketDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class TicketingTest extends FoundationTestCase
{
    private function network(string $tag = 'A'): array
    {
        $actor = $this->userWithRole('Ticketing Officer');
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'Private park address', 'status' => 'active']);
        $route = Route::create(['route_code' => $tag, 'origin' => 'Synthetic origin', 'destination' => 'Synthetic destination', 'status' => 'active']);
        $park->routes()->attach($route, ['status' => 'active', 'created_at' => now()]);
        $operator = Operator::create(['operator_number' => $tag, 'name' => 'Synthetic Operator '.$tag, 'phone' => 'PRIVATE-OP-PHONE', 'status' => 'approved']);
        $operator->parks()->attach($park, ['status' => 'active', 'created_at' => now()]);
        $operator->routes()->attach($route, ['park_id' => $park->id, 'status' => 'active', 'created_at' => now()]);
        $driver = Driver::create(['driver_number' => $tag, 'first_name' => 'PrivateFirst', 'last_name' => 'PrivateLast', 'phone' => 'PRIVATE-DRIVER-PHONE', 'licence_number' => 'PRIVATE-LICENCE', 'residential_address' => 'PRIVATE-RESIDENTIAL', 'status' => 'active']);
        $vehicle = Vehicle::create(['vehicle_number' => $tag, 'registration_number' => 'SYN-'.$tag, 'vehicle_type' => 'bus', 'owner_phone' => 'PRIVATE-OWNER-PHONE', 'status' => 'active']);
        $assignment = DriverAssignment::create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $operator->id, 'park_id' => $park->id, 'route_id' => $route->id, 'starts_at' => now()->subHour(), 'is_primary' => true, 'status' => 'active']);
        $actor->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);
        $head = RevenueHead::create(['code' => $tag, 'name' => 'Synthetic daily fee '.$tag, 'frequency' => 'daily', 'status' => 'active']);
        $fee = FeeConfiguration::create(['revenue_head_id' => $head->id, 'amount' => '500.10', 'currency' => 'NGN', 'effective_from' => now()->subDay(), 'priority' => 0, 'status' => 'active']);

        return compact('actor', 'lga', 'park', 'route', 'operator', 'driver', 'vehicle', 'assignment', 'head', 'fee');
    }

    private function data(array $n): array
    {
        $review = app(TicketIssuanceService::class)->review($n['actor'], $n['assignment']->id, $n['head']->id);

        return ['assignment_id' => $n['assignment']->id, 'revenue_head_id' => $n['head']->id, 'confirmation' => $review['confirmation'], 'request_key' => $review['terms']['request_key']];
    }

    private function issue(array $n): Ticket
    {
        return app(IssueTicketAction::class)->execute($n['actor'], IssueTicketData::fromArray($this->data($n)));
    }

    public function test_all_six_screens_render_with_backend_review_and_safe_public_qr(): void
    {
        $n = $this->network();
        $this->actingAs($n['actor'])->get('/tickets')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Index'));
        $query = http_build_query(['assignment_id' => $n['assignment']->id, 'revenue_head_id' => $n['head']->id]);
        $this->get('/tickets/create?'.$query)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Create')->where('review.amount', '500.10')->where('review.context_snapshot.vehicle.registration', 'SYN-A'));
        $ticket = $this->issue($n);
        $this->get('/tickets/'.$ticket->public_id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Show')->where('ticket.amount', '500.10')->missing('ticket.verification_token')->where('qr_image', fn ($value) => str_starts_with($value, 'data:image/svg+xml;base64,')));
        $this->get('/tickets/'.$ticket->public_id.'/print')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Print'));
        $this->actingAs($this->userWithRole('State Administrator'))->get('/tickets/'.$ticket->public_id.'/cancel')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Cancel'));
        $this->get('/verify/ticket/'.$ticket->verification_token)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/TicketVerification')->where('verification.valid', false)->where('verification.payment_status', 'unpaid'));
    }

    public function test_request_cannot_supply_its_own_amount_status_references_or_actor(): void
    {
        $n = $this->network();
        $this->actingAs($n['actor'])->post('/tickets', $this->data($n) + ['amount' => '0.01', 'issued_by' => 999, 'ticket_reference' => 'FORGED', 'verification_token' => 'forged', 'ticket_status' => 'paid', 'payment_status' => 'paid', 'expires_at' => '2099-01-01'])->assertSessionHasNoErrors();
        $ticket = Ticket::firstOrFail();
        $this->assertSame('500.10', $ticket->amount);
        $this->assertSame($n['actor']->id, $ticket->issued_by);
        $this->assertSame('pending', $ticket->ticket_status->value);
        $this->assertSame('unpaid', $ticket->payment_status->value);
        $this->assertNull($ticket->expires_at);
        $this->assertStringStartsWith('OSPM-'.now()->year.'-', $ticket->ticket_reference);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $ticket->verification_token);
        $this->assertDatabaseHas('activity_log', ['description' => 'ticket_created', 'causer_id' => $n['actor']->id]);
        $this->assertStringNotContainsString($ticket->verification_token, DB::table('activity_log')->pluck('properties')->implode(' '));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_and_inactive_assignments_are_rejected_at_issuance(): void
    {
        $n = $this->network();
        $data = $this->data($n);
        $this->actingAs($n['actor']);
        foreach (['driver', 'vehicle', 'park', 'lga', 'route'] as $key) {
            $n[$key]->update(['status' => 'inactive']);
            $this->post('/tickets', $data)->assertSessionHasErrors('assignment_id');
            $n[$key]->update(['status' => 'active']);
        }
        $n['operator']->update(['status' => 'suspended']);
        $this->post('/tickets', $data)->assertSessionHasErrors('assignment_id');
        $n['operator']->update(['status' => 'approved']);
        foreach ([['status' => 'ended'], ['starts_at' => now()->addHour()], ['ends_at' => now()->subSecond()]] as $change) {
            $n['assignment']->update($change);
            $this->post('/tickets', $data)->assertSessionHasErrors('assignment_id');
            $n['assignment']->update(['status' => 'active', 'starts_at' => now()->subHour(), 'ends_at' => null]);
        }
        DB::table('operator_route')->update(['status' => 'inactive']);
        $this->post('/tickets', $data)->assertSessionHasErrors('assignment_id');
        DB::table('operator_route')->update(['status' => 'active']);
        DB::table('operator_park')->update(['status' => 'inactive']);
        $this->post('/tickets', $data)->assertSessionHasErrors('assignment_id');
        $this->assertSame(0, Ticket::count());
    }

    public function test_replayed_review_submission_creates_one_ticket_and_tampered_confirmation_fails(): void
    {
        $n = $this->network();
        $data = $this->data($n);
        $this->actingAs($n['actor']);
        $this->post('/tickets', $data)->assertSessionHasNoErrors();
        $this->post('/tickets', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(1, DB::table('activity_log')->where('description', 'ticket_created')->count());
        $this->post('/tickets', array_replace($data, ['request_key' => str_repeat('a', 32)]))->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_fee_selection_ignores_forged_configuration_and_detects_changed_review(): void
    {
        $n = $this->network();
        $data = $this->data($n);
        $this->actingAs($n['actor']);
        $specific = FeeConfiguration::create(['revenue_head_id' => $n['head']->id, 'park_id' => $n['park']->id, 'vehicle_type' => 'bus', 'route_id' => $n['route']->id, 'amount' => '700.25', 'currency' => 'NGN', 'effective_from' => now()->subMinute(), 'priority' => 0, 'status' => 'active']);
        $this->post('/tickets', $data)->assertSessionHasErrors('confirmation');
        $this->post('/tickets', $this->data($n) + ['fee_configuration_id' => $n['fee']->id])->assertSessionHasNoErrors();
        $this->assertSame($specific->id, Ticket::first()->fee_configuration_id);
        $this->assertSame('700.25', Ticket::first()->amount);
        $specific->update(['status' => 'inactive']);
        $n['fee']->update(['status' => 'inactive']);
        $this->post('/tickets', $data)->assertSessionHasErrors('fee');
        $n['head']->update(['status' => 'inactive']);
        $this->post('/tickets', $data)->assertSessionHasErrors('revenue_head_id');
    }

    public function test_ticket_keeps_fee_and_context_after_future_fees_and_master_changes(): void
    {
        $n = $this->network();
        $ticket = $this->issue($n);
        $original = $ticket->fresh()->context_snapshot;
        $n['head']->update(['name' => 'Renamed fee', 'code' => 'NEW']);
        $n['park']->update(['name' => 'Renamed park']);
        $n['vehicle']->update(['registration_number' => 'RENAMED']);
        $n['driver']->update(['first_name' => 'Renamed']);
        $n['operator']->update(['name' => 'Renamed operator']);
        FeeConfiguration::create(['revenue_head_id' => $n['head']->id, 'amount' => '900.00', 'currency' => 'NGN', 'effective_from' => now()->addHour(), 'priority' => 1, 'status' => 'active']);
        $this->travel(2)->hours();
        $next = $this->issue($n);
        $this->assertSame('900.00', $next->amount);
        $this->assertSame('500.10', $ticket->fresh()->amount);
        $this->assertSame('A', $ticket->fresh()->fee_code_snapshot);
        $this->assertSame('Synthetic daily fee A', $ticket->fresh()->fee_name_snapshot);
        $this->assertSame($original, $ticket->fresh()->context_snapshot);
        $safe = app(TicketVerificationService::class)->safe($ticket->fresh());
        $this->assertSame('SYN-A', $safe['vehicle']);
        $this->assertSame('Synthetic Park A', $safe['park']);
        $this->travelBack();
    }

    public function test_ticket_model_prevents_snapshot_changes_and_deletion(): void
    {
        $ticket = $this->issue($this->network());
        foreach (['amount' => '0.00', 'context_snapshot' => [], 'issued_by' => 999] as $key => $value) {
            try {
                $ticket->fresh()->update([$key => $value]);
                $this->fail('Immutable field changed.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('immutable', $error->getMessage());
            }
        }
        try {
            $ticket->fresh()->delete();
            $this->fail('Ticket deleted.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('retained', $error->getMessage());
        }
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_unique_references_tokens_and_reference_collision_retry(): void
    {
        $n = $this->network();
        $first = $this->issue($n);
        $mock = \Mockery::mock(TicketReferenceService::class);
        $mock->shouldReceive('generate')->andReturn($first->ticket_reference, 'OSPM-RETRY-UNIQUE');
        $this->app->instance(TicketReferenceService::class, $mock);
        $second = $this->issue($n);
        $this->assertSame('OSPM-RETRY-UNIQUE', $second->ticket_reference);
        $this->assertNotSame($first->verification_token, $second->verification_token);
        $this->assertDatabaseCount('tickets', 2);
        $this->assertSame(2, DB::table('activity_log')->where('description', 'ticket_created')->count());
    }

    public function test_geographic_operator_and_view_manage_scopes_are_enforced(): void
    {
        $a = $this->network('A');
        $b = $this->network('B');
        $ta = $this->issue($a);
        $tb = $this->issue($b);
        $this->actingAs($a['actor'])->get('/tickets')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $ta->public_id));
        foreach (['', '/print', '/cancel'] as $suffix) {
            $this->get('/tickets/'.$tb->public_id.$suffix)->assertForbidden();
        }
        $this->post('/tickets', $this->data($b))->assertForbidden();
        $viewer = $this->userWithRole('Ticketing Officer');
        $viewer->parks()->attach($a['park'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($viewer)->post('/tickets', $this->data($a))->assertForbidden();
        $operatorUser = $this->userWithRole('Transport Operator');
        $operatorUser->operators()->attach($a['operator'], ['access_level' => 'view', 'created_at' => now()]);
        // A foreign operator's ticket at the very same park must remain private.
        DB::table('tickets')->where('id', $tb->id)->update(['park_id' => $a['park']->id, 'lga_id' => $a['lga']->id]);
        $this->actingAs($operatorUser)->get('/tickets')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/tickets/'.$tb->public_id)->assertForbidden();
        $this->post('/tickets', $this->data($a))->assertForbidden();
        $this->actingAs($this->userWithRole('Auditor'))->post('/tickets', $this->data($a))->assertForbidden();
    }

    public function test_cancellation_requires_supervisor_permission_reason_and_eligible_state(): void
    {
        $n = $this->network();
        $ticket = $this->issue($n);
        $path = '/tickets/'.$ticket->public_id.'/cancel';
        $this->actingAs($n['actor'])->patch($path, ['reason' => 'Denied collector cancellation'])->assertForbidden();
        $this->actingAs($this->userWithRole('Auditor'))->patch($path, ['reason' => 'Denied auditor cancellation'])->assertForbidden();
        $supervisor = $this->userWithRole('Park Manager');
        $supervisor->parks()->attach($n['park'], ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($supervisor)->patch($path, ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->patch($path, ['reason' => 'Synthetic duplicate entered by mistake'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $ticket->fresh()->ticket_status->value);
        $this->assertSame('500.10', $ticket->fresh()->amount);
        $event = DB::table('activity_log')->where('description', 'ticket_cancelled')->first();
        $this->assertSame($supervisor->id, $event->causer_id);
        $this->assertStringContainsString('Synthetic duplicate', $event->properties);
        $this->patch($path, ['reason' => 'Repeated cancellation'])->assertSessionHasErrors('reason');
        $this->assertSame(1, DB::table('activity_log')->where('description', 'ticket_cancelled')->count());
    }

    public function test_paid_pending_payment_reversed_and_expired_tickets_cannot_be_cancelled(): void
    {
        $n = $this->network();
        $admin = $this->userWithRole('State Administrator');
        $this->actingAs($admin);
        foreach ([['pending', 'pending'], ['paid', 'paid'], ['reversed', 'reversed'], ['expired', 'unpaid']] as [$status,$payment]) {
            $ticket = $this->issue($n);
            $ticket->update(['ticket_status' => $status, 'payment_status' => $payment]);
            $this->patch('/tickets/'.$ticket->public_id.'/cancel', ['reason' => 'Ineligible synthetic cancellation'])->assertSessionHasErrors('reason');
        }
    }

    public function test_expiry_is_immediate_in_verification_and_command_is_idempotent(): void
    {
        config(['ospm.ticket_expiry_minutes' => 15]);
        $n = $this->network();
        $ticket = $this->issue($n);
        $this->assertTrue($ticket->expires_at->greaterThan($ticket->issued_at));
        $this->travel(15)->minutes();
        $this->assertSame('expired', app(TicketVerificationService::class)->safe($ticket->fresh())['ticket_status']);
        $this->actingAs($n['actor'])->get('/tickets?ticket_status=expired')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.ticket_status', 'expired'));
        $this->get('/tickets?ticket_status=pending')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->artisan('ospm:tickets-expire')->expectsOutput('1 tickets expired.')->assertSuccessful();
        $this->artisan('ospm:tickets-expire')->expectsOutput('0 tickets expired.')->assertSuccessful();
        $this->assertSame(1, DB::table('activity_log')->where('description', 'ticket_expired')->count());
        $this->assertSame('500.10', $ticket->fresh()->amount);
        $this->travelBack();
    }

    public function test_public_statuses_allow_only_safe_fields_and_never_leak_logged_in_account(): void
    {
        $n = $this->network();
        $ticket = $this->issue($n);
        $url = '/verify/ticket/'.$ticket->verification_token;
        foreach ([['pending', 'unpaid', false], ['paid', 'paid', true], ['cancelled', 'unpaid', false], ['reversed', 'reversed', false], ['paid', 'refunded', false], ['expired', 'paid', false]] as [$status,$payment,$valid]) {
            $ticket->update(['ticket_status' => $status, 'payment_status' => $payment]);
            $this->actingAs($this->userWithRole('Super Administrator'))->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $p) => $p
                ->where('verification.valid', $valid)->where('auth.user', null)->has('navigation', 0)
                ->missing('verification.id')->missing('verification.public_id')->missing('verification.driver')->missing('verification.operator')
                ->missing('verification.context_snapshot')->missing('verification.verification_token'));
            $safe = json_encode(app(TicketVerificationService::class)->verify($ticket->verification_token));
            foreach (['PRIVATE-', 'PrivateFirst', 'PrivateLast', $ticket->public_id, $ticket->verification_token] as $secret) {
                $this->assertStringNotContainsString($secret, $safe);
            }
        }
    }

    public function test_unknown_and_malformed_verification_tokens_use_same_safe_response_and_rate_limit(): void
    {
        foreach (['not-a-token', str_repeat('a', 64)] as $token) {
            $this->get('/verify/ticket/'.$token)->assertNotFound()->assertInertia(fn (Assert $p) => $p->component('Public/TicketVerification')->where('verification', null));
        }
        for ($i = 0; $i < 58; $i++) {
            $this->get('/verify/ticket/not-a-token')->assertNotFound();
        }
        $this->get('/verify/ticket/not-a-token')->assertStatus(429);
    }

    public function test_ticket_list_pagination_search_sort_filters_and_parent_tabs_are_scoped(): void
    {
        $n = $this->network();
        for ($i = 0; $i < 17; $i++) {
            $this->issue($n);
        }
        $this->actingAs($n['actor'])->get('/tickets?search=SYN-A&sort=amount&direction=asc')->assertInertia(fn (Assert $p) => $p->has('records.data', 15)->where('records.total', 17)->missing('records.data.0.verification_token'));
        $this->get('/tickets?from=2099-01-01&to=2099-01-02')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/tickets?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/tickets?payment_status=paid')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/parks/'.$n['park']->public_id)->assertInertia(fn (Assert $p) => $p->where('transport.tickets.total', 17));
        $this->get('/parks/'.$n['park']->public_id.'/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.4.value', 17));
        foreach (['driver' => 'drivers', 'vehicle' => 'vehicles', 'operator' => 'operators'] as $key => $path) {
            $this->get('/'.$path.'/'.$n[$key]->public_id)->assertInertia(fn (Assert $p) => $p->where('related.tickets.total', 17));
        }
    }

    public function test_historical_lga_scope_does_not_follow_a_park_move(): void
    {
        $n = $this->network();
        $ticket = $this->issue($n);
        $other = Lga::create(['code' => 'B', 'name' => 'Other synthetic LGA', 'status' => 'active']);
        $n['park']->update(['lga_id' => $other->id]);
        $oldUser = $this->userWithRole('LGA Administrator');
        $oldUser->lgas()->attach($n['lga'], ['access_level' => 'manage', 'created_at' => now()]);
        $newUser = $this->userWithRole('LGA Administrator');
        $newUser->lgas()->attach($other, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($oldUser)->get('/tickets/'.$ticket->public_id)->assertOk();
        $this->actingAs($newUser)->get('/tickets/'.$ticket->public_id)->assertForbidden();
    }

    public function test_actions_authorize_without_http_and_synthetic_seeding_preserves_cancelled_ticket(): void
    {
        $n = $this->network();
        $data = IssueTicketData::fromArray($this->data($n));
        try {
            app(IssueTicketAction::class)->execute($this->userWithRole('Auditor'), $data);
            $this->fail('Action allowed auditor issuance.');
        } catch (AuthorizationException $error) {
            $this->assertTrue(true);
        }
        $ticket = $this->issue($n);
        try {
            app(CancelTicketAction::class)->execute($n['actor'], $ticket, 'Forbidden direct cancellation');
            $this->fail('Action allowed collector cancellation.');
        } catch (AuthorizationException $error) {
            $this->assertTrue(true);
        }
        config(['ospm.demo_mode' => true, 'ospm.demo_password' => 'SyntheticDemoPassword42']);
        $this->seed(DatabaseSeeder::class);
        $demo = Ticket::where('fee_code_snapshot', 'DEMO-DPT')->firstOrFail();
        $count = Ticket::count();
        app(CancelTicketAction::class)->execute(User::where('email', 'superadmin@demo.local')->firstOrFail(), $demo, 'Synthetic seeder history check');
        $this->seed(TicketDemoSeeder::class);
        $this->assertSame($count, Ticket::count());
        $this->assertSame('cancelled', $demo->fresh()->ticket_status->value);
    }
}
