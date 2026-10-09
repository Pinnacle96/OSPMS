<?php

namespace Tests\Feature\Complaints;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Complaints\Actions\CreateComplaintAction;
use App\Domains\Complaints\Actions\ManageComplaintAction;
use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Models\ComplaintNote;
use App\Domains\Drivers\Actions\SaveDriverAction;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Vehicles\Models\Vehicle;
use App\Notifications\ComplaintAssignedNotification;
use App\Notifications\DriverApprovedNotification;
use Database\Seeders\ComplaintDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\FoundationTestCase;

class ComplaintsNotificationsTest extends FoundationTestCase
{
    private function network(string $tag = 'A'): array
    {
        $l = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $p = Park::create(['lga_id' => $l->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'PRIVATE ADDRESS', 'status' => 'active']);
        $u = $this->userWithRole('Help Desk Officer');
        $u->parks()->attach($p, ['access_level' => 'view', 'created_at' => now()]);

        return ['park' => $p, 'user' => $u];
    }

    private function payload(array $n, array $extra = []): array
    {
        return array_replace(['complainant_name' => 'Synthetic Resident', 'complainant_phone' => '+234 800 000 0000', 'complainant_email' => 'resident@example.test', 'category' => 'Service', 'description' => 'Synthetic complaint observation for review.', 'park' => $n['park']->public_id, 'idempotency_key' => bin2hex(random_bytes(32))], $extra);
    }

    private function complaint(array $n, array $extra = []): Complaint
    {
        return app(CreateComplaintAction::class)->execute($n['user'], $this->payload($n, $extra));
    }

    private function change(Complaint $c, array $extra = []): array
    {
        $c = $c->fresh();

        return array_replace(['action' => 'status', 'status' => 'received', 'reason' => 'Synthetic routing and review reason.', 'expected_status' => $c->status->value, 'expected_assignee' => $c->assignee?->public_id, 'idempotency_key' => bin2hex(random_bytes(32))], $extra);
    }

    private function move(array $n, Complaint $c, array $extra = []): Complaint
    {
        return app(ManageComplaintAction::class)->execute($n['user'], $c, $this->change($c, $extra));
    }

    private function assigned(array $n): Complaint
    {
        $c = $this->complaint($n);
        $this->move($n, $c);

        return $this->move($n, $c, ['action' => 'assign', 'assignee' => $n['user']->public_id]);
    }

    private function postChange(array $n, Complaint $c, array $data)
    {
        return $this->actingAs($n['user'])->post('/complaints/'.$c->public_id.'/manage', $data);
    }

    public function test_exact_approved_schema(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'public_id', 'complaint_reference', 'complainant_name', 'complainant_phone', 'complainant_email', 'category', 'park_id', 'operator_id', 'driver_id', 'vehicle_id', 'description', 'source', 'status', 'assigned_to', 'submitted_by', 'resolution', 'resolved_at', 'created_at', 'updated_at'], Schema::getColumnListing('complaints'));
        $this->assertEqualsCanonicalizing(['id', 'complaint_id', 'user_id', 'note', 'is_internal', 'created_at'], Schema::getColumnListing('complaint_notes'));
        $this->assertEqualsCanonicalizing(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'], Schema::getColumnListing('notifications'));
    }

    public function test_seven_screens_and_private_headers(): void
    {
        $n = $this->network();
        $c = $this->assigned($n);
        foreach (['/complaints' => 'Complaints/Index', '/complaints/create' => 'Complaints/Create', '/complaints/'.$c->public_id => 'Complaints/Show', '/notifications' => 'Notifications/Index', '/account/notifications' => 'Account/Notifications', '/public/complaints' => 'Public/ComplaintForm'] as $url => $page) {
            $r = $this->actingAs($n['user'])->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($page));
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
            $this->assertTrue($r->viewData('page')['encryptHistory']);
        }
        $this->withSession(['public_complaint_reference' => $c->complaint_reference])->get('/public/complaints/success')->assertInertia(fn (Assert $p) => $p->component('Public/ComplaintSuccess')->where('reference', $c->complaint_reference));
    }

    public function test_staff_identity_reference_status_and_source_ignore_forgery(): void
    {
        $n = $this->network();
        $this->actingAs($n['user'])->post('/complaints', $this->payload($n, ['submitted_by' => 999, 'assigned_to' => 999, 'status' => 'closed', 'source' => 'field', 'complaint_reference' => 'FORGED', 'resolution' => 'FORGED', 'resolved_at' => now()->toIso8601String()]))->assertStatus(303)->assertSessionHasNoErrors();
        $c = Complaint::sole();
        $this->assertSame($n['user']->id, $c->submitted_by);
        $this->assertNull($c->assigned_to);
        $this->assertNull($c->resolution);
        $this->assertSame('help_desk', $c->source->value);
        $this->assertSame('submitted', $c->status->value);
        $this->assertStringStartsWith('CMP-', $c->complaint_reference);
    }

    public function test_public_submission_redacts_authenticated_context_and_success(): void
    {
        $n = $this->network();
        $r = $this->actingAs($n['user'])->get('/public/complaints')->assertInertia(fn (Assert $p) => $p->where('auth.user', null)->where('navigation', [])->where('unread_notifications_count', 0)->missing('contexts')->where('parks.0', fn ($p) => $p->keys()->all() === ['public_id', 'name']));
        $key = $r->viewData('page')['props']['idempotency_key'];
        $this->post('/public/complaints', $this->payload($n, ['idempotency_key' => $key]))->assertStatus(303)->assertSessionHasNoErrors();
        $c = Complaint::sole();
        $this->assertNull($c->submitted_by);
        $this->assertSame('public_web', $c->source->value);
        $this->get('/public/complaints/success?reference=FORGED')->assertInertia(fn (Assert $p) => $p->where('reference', $c->complaint_reference)->missing('record')->missing('complainant_email')->where('auth.user', null));
    }

    public function test_public_requires_session_confirmation_and_success_requires_session_reference(): void
    {
        $n = $this->network();
        $this->post('/public/complaints', $this->payload($n))->assertSessionHasErrors('idempotency_key');
        $this->get('/public/complaints/success?reference=CMP-FORGED')->assertRedirect('/public/complaints');
        $this->assertSame(0, Complaint::count());
    }

    public function test_public_confirmation_cannot_be_replayed_in_another_session(): void
    {
        $n = $this->network();
        $key = bin2hex(random_bytes(32));
        $d = $this->payload($n, ['idempotency_key' => $key]);
        $c = app(CreateComplaintAction::class)->execute(null, $d, 'session-a');
        $this->expectException(ValidationException::class);
        app(CreateComplaintAction::class)->execute(null, $d, 'session-b');
    }

    public function test_public_rate_limit_is_enforced(): void
    {
        $n = $this->network();
        $r = $this->get('/public/complaints');
        $key = $r->viewData('page')['props']['idempotency_key'];
        $d = $this->payload($n, ['idempotency_key' => $key]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/public/complaints', $d)->assertStatus(303);
        }$this->post('/public/complaints', $d)->assertStatus(429);
        $this->assertSame(1, Complaint::count());
    }

    public function test_public_cannot_select_private_operating_context(): void
    {
        $n = $this->network();
        $key = $this->get('/public/complaints')->viewData('page')['props']['idempotency_key'];
        $this->post('/public/complaints', $this->payload($n, ['idempotency_key' => $key, 'context' => 'FORGED']))->assertSessionHasErrors('context');
        $this->assertSame(0, Complaint::count());
    }

    public function test_public_and_staff_validate_trimmed_required_fields_and_contacts(): void
    {
        $n = $this->network();
        foreach (['complainant_name' => '  ', 'category' => '  ', 'description' => '          ', 'complainant_email' => 'not-email', 'complainant_phone' => 'script()'] as $field => $value) {
            $this->actingAs($n['user'])->post('/complaints', $this->payload($n, [$field => $value]))->assertSessionHasErrors($field);
        }$this->assertSame(0, Complaint::count());
    }

    public function test_public_only_lists_active_parks_without_addresses(): void
    {
        $n = $this->network();
        $b = $this->network('B');
        $b['park']->update(['status' => 'inactive']);
        $this->get('/public/complaints')->assertInertia(fn (Assert $p) => $p->has('parks', 1)->where('parks.0.name', $n['park']->name)->missing('parks.0.address'));
        $key = bin2hex(random_bytes(32));
        $this->withSession(['public_complaint_confirmations' => [$key]])->post('/public/complaints', $this->payload($b, ['idempotency_key' => $key]))->assertSessionHasErrors('park');
    }

    public function test_view_grant_is_enough_for_helpdesk_but_not_cross_park(): void
    {
        $n = $this->network();
        $b = $this->network('B');
        $c = $this->complaint($b);
        $this->actingAs($n['user'])->post('/complaints', $this->payload($b))->assertSessionHasErrors('park');
        $this->get('/complaints/'.$c->public_id)->assertForbidden();
        $this->postChange($n, $c, $this->change($c))->assertForbidden();
    }

    public function test_revoked_scope_denies_detail_notes_evidence_and_replay(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $d = $this->payload($n, ['file' => UploadedFile::fake()->image('evidence.png')]);
        $c = app(CreateComplaintAction::class)->execute($n['user'], $d);
        $n['user']->parks()->detach();
        $this->actingAs($n['user'])->get('/complaints/'.$c->public_id)->assertForbidden();
        $this->get('/complaints/'.$c->public_id.'/evidence/'.$c->evidence()->sole()->public_id)->assertForbidden();
        $this->post('/complaints', $d)->assertSessionHasErrors('park');
        $this->postChange($n, $c, $this->change($c, ['action' => 'note', 'note' => 'Denied note']))->assertForbidden();
    }

    public function test_unlocated_public_complaint_is_statewide_until_assigned(): void
    {
        $n = $this->network();
        $c = app(CreateComplaintAction::class)->execute(null, $this->payload($n, ['park' => null]), 'public-session');
        $this->actingAs($n['user'])->get('/complaints/'.$c->public_id)->assertForbidden();
        $super = $this->userWithRole('Super Administrator');
        $this->actingAs($super)->get('/complaints/'.$c->public_id)->assertOk();
        app(ManageComplaintAction::class)->execute($super, $c, $this->change($c));
        app(ManageComplaintAction::class)->execute($super, $c, $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]));
        $this->actingAs($n['user'])->get('/complaints/'.$c->public_id)->assertOk();
    }

    public function test_staff_unlocated_complaint_is_visible_to_its_submitter(): void
    {
        $n = $this->network();
        $c = $this->complaint($n, ['park' => null]);
        $this->actingAs($n['user'])->get('/complaints/'.$c->public_id)->assertOk();
    }

    public function test_read_only_role_matrix(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        foreach (['State Administrator', 'Auditor', 'LGA Administrator', 'Park Manager', 'Enforcement Officer'] as $role) {
            $u = $this->userWithRole($role);
            $u->parks()->attach($n['park'], ['access_level' => 'manage', 'created_at' => now()]);
            $this->actingAs($u)->get('/complaints/'.$c->public_id)->assertInertia(fn (Assert $p) => $p->where('can_manage', false)->where('record.contact', null));
            $this->post('/complaints/'.$c->public_id.'/manage', $this->change($c))->assertForbidden();
            $this->get('/complaints/create')->assertForbidden();
        }
        foreach (['Executive Viewer', 'Finance Administrator', 'Revenue Officer', 'Ticketing Officer', 'Collection Agent'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/complaints')->assertForbidden();
        }
    }

    public function test_context_is_derived_together_and_operator_view_is_own_only(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $o = Operator::create(['operator_number' => 'A', 'name' => 'Synthetic operator', 'status' => 'approved']);
        $o->parks()->attach($n['park'], ['status' => 'active', 'created_at' => now()]);
        $d = Driver::create(['driver_number' => 'A', 'first_name' => 'Synthetic', 'last_name' => 'Driver', 'phone' => 'PRIVATE', 'status' => 'active']);
        $v = Vehicle::create(['vehicle_number' => 'A', 'registration_number' => 'A', 'vehicle_type' => 'bus', 'status' => 'active']);
        DriverAssignment::create(['park_id' => $n['park']->id, 'driver_id' => $d->id, 'vehicle_id' => $v->id, 'operator_id' => $o->id, 'status' => 'active', 'starts_at' => now()->subHour(), 'is_primary' => true]);
        $ctx = app(FieldLookupService::class)->contexts($n['user'])->first()['context'];
        $c = $this->complaint($n, ['context' => $ctx, 'operator_id' => 999, 'file' => UploadedFile::fake()->image('private.png')]);
        $this->assertSame($o->id, $c->operator_id);
        $this->assertSame($d->id, $c->driver_id);
        $this->assertSame($v->id, $c->vehicle_id);
        $this->move($n, $c, ['action' => 'note', 'note' => 'PRIVATE INTERNAL NOTE']);
        $this->move($n, $c, ['action' => 'note', 'note' => 'Shared service update', 'is_internal' => false]);
        $u = $this->userWithRole('Transport Operator');
        $u->operators()->attach($o, ['access_level' => 'view', 'created_at' => now()]);
        $u->parks()->attach($n['park'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($u)->get('/complaints/'.$c->public_id)->assertInertia(fn (Assert $p) => $p->where('record.contact', null)->where('record.evidence', [])->has('record.notes', 1)->where('record.notes.0.note', 'Shared service update')->where('record.history', []));
        $this->get('/complaints/'.$c->public_id.'/evidence/'.$c->evidence()->sole()->public_id)->assertForbidden();
        $other = $this->complaint($n);
        $this->get('/complaints/'.$other->public_id)->assertForbidden();
    }

    public function test_assignment_requires_received_status_active_manager_and_matching_scope(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->postChange($n, $c, $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]))->assertSessionHasErrors('assignee');
        $this->move($n, $c);
        $b = $this->network('B');
        foreach ([$b['user'], $this->userWithRole('Park Manager')] as $u) {
            $this->postChange($n, $c, $this->change($c, ['action' => 'assign', 'assignee' => $u->public_id]))->assertSessionHasErrors('assignee');
        }$n['user']->update(['status' => 'inactive']);
        $super = $this->userWithRole('Super Administrator');
        $this->actingAs($super)->post('/complaints/'.$c->public_id.'/manage', $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]))->assertSessionHasErrors('assignee');
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_full_workflow_retains_author_time_notes_resolution_and_closure(): void
    {
        $n = $this->network();
        $c = $this->assigned($n);
        $this->move($n, $c, ['action' => 'note', 'note' => 'Synthetic internal note', 'user_id' => 999, 'created_at' => '2000-01-01']);
        $note = ComplaintNote::sole();
        $this->assertSame($n['user']->id, $note->user_id);
        $this->assertTrue($note->is_internal);
        $this->assertTrue($note->created_at->isToday());
        $this->move($n, $c, ['status' => 'under_review']);
        $this->move($n, $c, ['status' => 'resolved', 'resolution' => 'Synthetic resolution retained for review.']);
        $resolved = $c->fresh()->resolved_at;
        $this->move($n, $c, ['status' => 'closed']);
        $this->assertSame('closed', $c->fresh()->status->value);
        $this->assertSame($resolved->toIso8601String(), $c->fresh()->resolved_at->toIso8601String());
        $this->assertSame(4, Activity::where('description', 'complaint_status_changed')->count());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_workflow_rejects_skipping_and_terminal_mutations(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->postChange($n, $c, $this->change($c, ['status' => 'resolved', 'resolution' => 'Forged early resolution.']))->assertSessionHasErrors('status');
        $c = $this->assigned($n);
        $this->move($n, $c, ['status' => 'under_review']);
        $this->postChange($n, $c, $this->change($c, ['status' => 'resolved']))->assertSessionHasErrors('resolution');
        $this->move($n, $c, ['status' => 'resolved', 'resolution' => 'Proper retained resolution.']);
        $this->postChange($n, $c, $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]))->assertSessionHasErrors('assignee');
        $this->move($n, $c, ['status' => 'closed']);
        $this->postChange($n, $c, $this->change($c, ['action' => 'note', 'note' => 'Late note']))->assertSessionHasErrors('note');
    }

    public function test_stale_status_and_assignee_prevent_lost_decisions(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $old = $this->change($c);
        $this->move($n, $c);
        $this->postChange($n, $c, $old)->assertSessionHasErrors('expected_status');
        $old = $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]);
        $this->move($n, $c, ['action' => 'assign', 'assignee' => $n['user']->public_id]);
        $this->postChange($n, $c, $old)->assertSessionHasErrors('expected_status');
    }

    public function test_assignment_replay_is_one_decision_and_notification_even_after_read(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->move($n, $c);
        $d = $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]);
        $this->postChange($n, $c, $d)->assertStatus(303);
        $notice = $n['user']->notifications()->sole();
        $notice->markAsRead();
        $this->postChange($n, $c, $d)->assertStatus(303);
        $this->assertSame(1, Activity::where('description', 'complaint_assigned')->count());
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertNotNull($notice->fresh()->read_at);
        $this->postChange($n, $c, [...$d, 'reason' => 'Changed payload for the same key.'])->assertSessionHasErrors('idempotency_key');
    }

    public function test_note_replay_and_original_models_are_immutable(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $d = $this->change($c, ['action' => 'note', 'note' => 'Retained internal note']);
        $this->postChange($n, $c, $d)->assertStatus(303);
        $this->postChange($n, $c, $d)->assertStatus(303);
        $this->assertSame(1, ComplaintNote::count());
        $this->expectException(\LogicException::class);
        ComplaintNote::sole()->update(['note' => 'Changed']);
    }

    public function test_original_report_cannot_be_edited(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->expectException(\LogicException::class);
        $c->update(['description' => 'Changed observation']);
    }

    public function test_resolved_resolution_cannot_be_changed(): void
    {
        $n = $this->network();
        $c = $this->assigned($n);
        $this->move($n, $c, ['status' => 'under_review']);
        $c = $this->move($n, $c, ['status' => 'resolved', 'resolution' => 'Retained original resolution.']);
        $this->expectException(\LogicException::class);
        $c->update(['resolution' => 'Changed resolution']);
    }

    public function test_complaints_cannot_be_deleted(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->expectException(\LogicException::class);
        $c->delete();
    }

    public function test_private_evidence_replay_and_download_and_parent_matching(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $file = UploadedFile::fake()->image('proof.png');
        $d = $this->payload($n, ['file' => $file]);
        $c = app(CreateComplaintAction::class)->execute(null, $d, 'session-a');
        $again = app(CreateComplaintAction::class)->execute(null, $d, 'session-a');
        $this->assertSame($c->id, $again->id);
        $m = $c->evidence()->sole();
        $this->assertNull($m->uploaded_by);
        $this->assertCount(1, Storage::disk('local')->allFiles('evidence'));
        $r = $this->actingAs($n['user'])->get('/complaints/'.$c->public_id.'/evidence/'.$m->public_id)->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $other = $this->complaint($n);
        $this->get('/complaints/'.$other->public_id.'/evidence/'.$m->public_id)->assertNotFound();
        $this->post('/complaints', $this->payload($n, ['file' => UploadedFile::fake()->create('script.html', 2, 'text/html')]))->assertSessionHasErrors('file');
        $this->post('/complaints', $this->payload($n, ['file' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')]))->assertSessionHasErrors('file');
    }

    public function test_changed_evidence_cannot_reuse_confirmation(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $d = $this->payload($n, ['file' => UploadedFile::fake()->image('a.png', 10, 10)]);
        $c = app(CreateComplaintAction::class)->execute($n['user'], $d);
        $this->actingAs($n['user'])->post('/complaints', [...$d, 'file' => UploadedFile::fake()->image('a.png', 20, 20)])->assertSessionHasErrors('idempotency_key');
        $this->assertCount(1, Storage::disk('local')->allFiles('evidence'));
    }

    public function test_notification_failure_rolls_back_assignment_history_confirmation(): void
    {
        $n = $this->network();
        $c = $this->complaint($n);
        $this->move($n, $c);
        $d = $this->change($c, ['action' => 'assign', 'assignee' => $n['user']->public_id]);
        Event::listen(NotificationSending::class, fn () => throw new \RuntimeException('Synthetic delivery failure'));
        try {
            app(ManageComplaintAction::class)->execute($n['user'], $c, $d);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic delivery failure', $e->getMessage());
        }
        $this->assertSame('received', $c->fresh()->status->value);
        $this->assertNull($c->fresh()->assigned_to);
        $this->assertSame(0, Activity::where('description', 'complaint_assigned')->count());
        $this->assertFalse(DB::table('idempotency_keys')->where('idempotency_key', $d['idempotency_key'])->exists());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_notification_center_owns_records_and_get_does_not_mark_read(): void
    {
        $n = $this->network();
        $c = $this->assigned($n);
        $id = $n['user']->notifications()->sole()->id;
        $other = $this->userWithRole('Super Administrator');
        $this->actingAs($other)->get('/notifications')->assertInertia(fn (Assert $p) => $p->has('notifications.data', 0));
        $this->post('/notifications/'.$id.'/read')->assertNotFound();
        $this->actingAs($n['user'])->get('/notifications')->assertInertia(fn (Assert $p) => $p->has('notifications.data', 1)->where('unread_notifications_count', 1)->where('notifications.data.0.href', '/complaints/'.$c->public_id));
        $this->assertNull($n['user']->notifications()->sole()->read_at);
        $this->post('/notifications/'.$id.'/read')->assertStatus(303);
        $this->post('/notifications/'.$id.'/read')->assertStatus(303);
        $this->get('/notifications?read=unread')->assertInertia(fn (Assert $p) => $p->has('notifications.data', 0)->where('unread_notifications_count', 0));
        $this->get('/notifications?read=read')->assertInertia(fn (Assert $p) => $p->has('notifications.data', 1));
    }

    public function test_revoked_notification_subject_redacts_reference_and_link(): void
    {
        $n = $this->network();
        $c = $this->assigned($n);
        $raw = $n['user']->notifications()->sole()->data;
        $this->assertEqualsCanonicalizing(['kind', 'subject', 'reference', 'title'], array_keys($raw));
        $n['user']->parks()->detach();
        $this->actingAs($n['user'])->get('/notifications')->assertInertia(fn (Assert $p) => $p->where('notifications.data.0.href', null)->where('notifications.data.0.reference', null)->where('notifications.data.0.title', 'Record unavailable in your current scope'));
        $this->get('/complaints/'.$c->public_id)->assertForbidden();
    }

    public function test_mark_all_changes_only_current_user_and_preferences_need_no_role(): void
    {
        $n = $this->network();
        $this->assigned($n);
        $b = $this->network('B');
        $this->assigned($b);
        $this->actingAs($n['user'])->post('/notifications/read-all')->assertStatus(303);
        $this->assertSame(0, $n['user']->unreadNotifications()->count());
        $this->assertSame(1, $b['user']->unreadNotifications()->count());
        $u = User::factory()->create();
        $this->actingAs($u)->get('/account/notifications')->assertOk();
        $this->get('/notifications')->assertOk();
        $this->assertFalse(Schema::hasColumn('users', 'notification_preferences'));
    }

    public function test_notification_filter_validation_and_inactive_account(): void
    {
        $n = $this->network();
        $this->actingAs($n['user'])->get('/notifications?read=forged')->assertSessionHasErrors('read');
        $n['user']->update(['status' => 'inactive']);
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_expiry_notifications_known_dates_only_deduplicate_without_status_writes(): void
    {
        $u = $this->userWithRole('Super Administrator');
        $d = Driver::create(['driver_number' => 'EXPIRED', 'first_name' => 'Synthetic', 'last_name' => 'Expired', 'phone' => 'PRIVATE', 'created_by' => $u->id, 'licence_expiry' => now()->subDay()->toDateString(), 'status' => 'active']);
        $v = Vehicle::create(['vehicle_number' => 'EXPIRED', 'registration_number' => 'EXP', 'vehicle_type' => 'bus', 'created_by' => $u->id, 'insurance_expiry' => now()->subDay()->toDateString(), 'roadworthiness_expiry' => null, 'status' => 'active']);
        $this->artisan('notifications:expired-documents')->assertSuccessful();
        $this->artisan('notifications:expired-documents')->assertSuccessful();
        $this->assertSame(2, $u->notifications()->count());
        $this->assertSame('active', $d->fresh()->status->value);
        $this->assertSame('active', $v->fresh()->status->value);
        $d->update(['licence_expiry' => now()->addYear()->toDateString()]);
        $this->artisan('notifications:expired-documents')->assertSuccessful();
        $this->assertSame(2, $u->notifications()->count());
    }

    public function test_driver_approval_creates_database_notification_in_real_workflow(): void
    {
        $u = $this->userWithRole('Super Administrator');
        $d = app(SaveDriverAction::class)->execute($u, ['first_name' => 'Synthetic', 'last_name' => 'Approved', 'phone' => '01234567890', 'status' => 'active']);
        $this->assertSame(1,$u->notifications()->count());
        $this->assertSame(DriverApprovedNotification::class,$u->notifications()->sole()->type);
        $this->assertSame($d->public_id,$u->notifications()->sole()->data['subject']);
    }

    public function test_demo_seed_is_idempotent_and_retains_read_state_and_resolution(): void
    {
        config(['ospm.demo_mode' => true]);
        $this->seed(DatabaseSeeder::class);
        $c = Complaint::sole();
        $u = User::findOrFail($c->assigned_to);
        $n = $u->notifications()->where('type',ComplaintAssignedNotification::class)->sole();
        $n->markAsRead();
        $this->seed(ComplaintDemoSeeder::class);
        $this->assertSame(1,Complaint::count());
        $this->assertSame(1,ComplaintNote::count());
        $this->assertNotNull($n->fresh()->read_at);
        $this->assertSame(1,$u->notifications()->where('type',ComplaintAssignedNotification::class)->count());
    }
}
