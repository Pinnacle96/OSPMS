<?php

namespace Tests\Feature\Incidents;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Enforcement\Actions\RecordViolationAction;
use App\Domains\Enforcement\Actions\ResolveViolationAction;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Models\Violation;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Actions\CreateIncidentAction;
use App\Domains\Incidents\Actions\ManageIncidentAction;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\EvidenceService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Vehicles\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IncidentDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\FoundationTestCase;

class IncidentsViolationsTest extends FoundationTestCase
{
    private function network(string $tag = 'A'): array
    {
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);

        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'Synthetic private park address', 'status' => 'active']);

        $operator = Operator::create(['operator_number' => $tag, 'name' => 'Synthetic Operator '.$tag, 'status' => 'approved', 'phone' => 'PRIVATE-PHONE']);

        $operator->parks()->attach($park, ['status' => 'active', 'created_at' => now()]);

        $driver = Driver::create(['driver_number' => $tag, 'first_name' => 'Synthetic', 'last_name' => $tag, 'phone' => 'PRIVATE-DRIVER', 'status' => 'active', 'licence_number' => 'PRIVATE-LICENCE']);

        $vehicle = Vehicle::create(['vehicle_number' => $tag, 'registration_number' => 'SYN-'.$tag, 'vehicle_type' => 'bus', 'status' => 'active', 'owner_phone' => 'PRIVATE-OWNER']);

        $assignment = DriverAssignment::create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $operator->id, 'park_id' => $park->id, 'starts_at' => now()->subHour(), 'is_primary' => true, 'status' => 'active']);

        $officer = $this->userWithRole('Enforcement Officer');
        $officer->parks()->attach($park, ['access_level' => 'view', 'created_at' => now()]);

        $manager = $this->userWithRole('Park Manager');
        $manager->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);

        $inspection = Inspection::create(['inspection_reference' => 'INS-'.$tag, 'officer_user_id' => $officer->id, 'park_id' => $park->id, 'operator_id' => $operator->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'inspection_type' => 'vehicle_check', 'result' => 'requires_review', 'notes' => 'Synthetic retained observation.', 'occurred_at' => now(), 'created_at' => now()]);

        return compact('lga', 'park', 'operator', 'driver', 'vehicle', 'assignment', 'officer', 'manager', 'inspection');

    }

    private function spoof(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ospm-test');
        file_put_contents($path, '<html>script</html>');

        return new UploadedFile($path, 'spoof.jpg', null, null, true);
    }

    private function payload(array $n, array $extra = []): array
    {
        return array_replace(['park' => $n['park']->public_id, 'category' => 'safety_issue', 'description' => 'Synthetic observation for supervisor review.', 'occurred_at' => now()->setTimezone('Africa/Lagos')->subMinute()->format('Y-m-d\TH:i'), 'idempotency_key' => bin2hex(random_bytes(32))], $extra);
    }

    private function incident(array $n, array $extra = []): Incident
    {
        return app(CreateIncidentAction::class)->execute($n['officer'], $this->payload($n, $extra));
    }

    private function violation(array $n): Violation
    {
        return app(RecordViolationAction::class)->execute($n['officer'], $n['inspection'], ['category' => 'Observed issue', 'description' => 'Synthetic issue requiring review.', 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    private function change(Incident $i, string $status, array $extra = []): array
    {
        return array_replace(['expected_status' => $i->fresh()->status->value, 'status' => $status, 'resolution' => 'Synthetic reason with sufficient detail.', 'idempotency_key' => bin2hex(random_bytes(32))], $extra);
    }

    public function test_all_nine_screens_and_private_history_headers(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $v = $this->violation($n);

        $paths = ['/field/incidents/create' => 'Field/Incidents/Create', '/inspections' => 'Inspections/Index', '/inspections/'.$n['inspection']->public_id => 'Inspections/Show', '/violations' => 'Violations/Index', '/violations/'.$v->public_id => 'Violations/Show', '/incidents' => 'Incidents/Index', '/incidents/create' => 'Incidents/Create', '/incidents/'.$i->public_id => 'Incidents/Show'];

        foreach ($paths as $url => $component) {
            $response = $this->actingAs($n['officer'])->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertTrue($response->viewData('page')['encryptHistory']);
        }
        $this->actingAs($n['manager'])->get('/incidents/'.$i->public_id.'/manage')->assertInertia(fn (Assert $p) => $p->component('Incidents/Manage')->where('transitions', ['under_review', 'escalated']));

    }

    public function test_report_derives_identity_relationships_and_reference_ignoring_forgery(): void
    {
        $n = $this->network();
        $context = app(FieldLookupService::class)->contexts($n['officer'])->first()['context'];

        $this->actingAs($n['officer'])->post('/field/incidents', $this->payload($n, ['context' => $context, 'reporter_user_id' => 999, 'driver_id' => 999, 'status' => 'closed', 'incident_reference' => 'FORGED']))->assertStatus(303)->assertSessionHasNoErrors();

        $i = Incident::sole();
        $this->assertSame($n['officer']->id, $i->reporter_user_id);
        $this->assertSame($n['driver']->id, $i->driver_id);
        $this->assertSame($n['vehicle']->id, $i->vehicle_id);
        $this->assertSame($n['operator']->id, $i->operator_id);
        $this->assertSame('reported', $i->status->value);
        $this->assertStringStartsWith('INC-', $i->incident_reference);

        $this->assertSame(1, Activity::where('description', 'incident_reported')->count());

    }

    public function test_park_only_report_and_occurrence_time_are_retained(): void
    {
        $n = $this->network();
        $i = $this->incident($n, ['occurred_at' => '2025-01-02T03:04']);
        $this->assertNull($i->operator_id);
        $this->assertNull($i->driver_id);
        $this->assertSame('2025-01-02 02:04:00', $i->occurred_at->format('Y-m-d H:i:s'));

    }

    public function test_cross_scope_and_mismatched_assignments_are_rejected(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $context = app(FieldLookupService::class)->contexts($b['officer'])->first()['context'];

        $this->actingAs($a['officer'])->post('/incidents', $this->payload($b))->assertSessionHasErrors('park');

        $this->post('/incidents', $this->payload($a, ['context' => $context]))->assertSessionHasErrors('context');

        $a['officer']->parks()->attach($b['park'], ['access_level' => 'view', 'created_at' => now()]);

        $this->post('/incidents', $this->payload($a, ['context' => $context]))->assertSessionHasErrors('context');
        $this->assertDatabaseCount('incidents', 0);

    }

    public function test_ended_context_and_inactive_park_cannot_be_used(): void
    {
        $n = $this->network();
        $context = app(FieldLookupService::class)->contexts($n['officer'])->first()['context'];
        $n['assignment']->update(['status' => 'ended']);

        $this->actingAs($n['officer'])->post('/incidents', $this->payload($n, ['context' => $context]))->assertSessionHasErrors('context');

        $n['park']->update(['status' => 'inactive']);
        $this->post('/incidents', $this->payload($n))->assertSessionHasErrors('park');

    }

    public function test_input_rejects_future_dates_unknown_categories_whitespace_and_keys(): void
    {
        $n = $this->network();
        foreach ([['occurred_at' => '2099-01-01T01:01'], ['category' => 'statutory_offence'], ['description' => str_repeat(' ', 20)], ['idempotency_key' => 'invalid'], ['park' => 'internal-id']] as $bad) {
            $this->actingAs($n['officer'])->post('/incidents', $this->payload($n, $bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('incidents', 0);

    }

    public function test_reports_replay_once_and_bind_actor_payload_and_bytes(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $p = $this->payload($n, ['file' => UploadedFile::fake()->image('observation.jpg')]);
        $i = app(CreateIncidentAction::class)->execute($n['officer'], $p);
        $again = app(CreateIncidentAction::class)->execute($n['officer'], $p);
        $this->assertSame($i->id, $again->id);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('evidence'));

        $this->actingAs($n['officer'])->post('/incidents', array_replace($p, ['description' => 'Different retained observation.']))->assertSessionHasErrors('idempotency_key');

        $this->post('/incidents', array_replace($p, ['file' => UploadedFile::fake()->image('different.jpg')]))->assertSessionHasErrors('idempotency_key');

        $this->actingAs($n['manager'])->post('/incidents', $p)->assertSessionHasErrors('idempotency_key');

    }

    public function test_replay_rechecks_revoked_scope_before_returning_existing_report(): void
    {
        $n = $this->network();
        $p = $this->payload($n);
        app(CreateIncidentAction::class)->execute($n['officer'], $p);
        $n['officer']->parks()->detach();
        $this->actingAs($n['officer'])->post('/incidents', $p)->assertSessionHasErrors('park');
        $this->assertDatabaseCount('incidents', 1);

    }

    public function test_role_matrix_read_only_viewers_and_finance_exclusions(): void
    {
        $n = $this->network();
        $i = $this->incident($n);

        foreach (['Auditor', 'Executive Viewer', 'Help Desk Officer'] as $role) {
            $u = $this->userWithRole($role);
            $u->parks()->attach($n['park'], ['access_level' => 'manage', 'created_at' => now()]);
            $this->actingAs($u)->get('/incidents/'.$i->public_id)->assertOk();
            $this->post('/incidents', $this->payload($n))->assertForbidden();
            $this->get('/incidents/'.$i->public_id.'/manage')->assertForbidden();
        }
        foreach (['Finance Administrator', 'Revenue Officer', 'Ticketing Officer', 'Collection Agent'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/incidents')->assertForbidden();
        }

        $this->actingAs($n['officer'])->get('/incidents/'.$i->public_id.'/manage')->assertForbidden();

    }

    public function test_manage_access_requires_manage_pivot_not_read_access(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $n['manager']->parks()->updateExistingPivot($n['park']->id, ['access_level' => 'view']);
        $this->actingAs($n['manager'])->get('/incidents/'.$i->public_id)->assertOk();
        $this->get('/incidents/'.$i->public_id.'/manage')->assertForbidden();
        $this->post('/incidents/'.$i->public_id.'/manage', $this->change($i, 'under_review'))->assertForbidden();

    }

    public function test_cross_park_lists_details_status_actions_and_files_remain_scoped(): void
    {
        Storage::fake('local');
        $a = $this->network();
        $b = $this->network('B');
        $i = $this->incident($b, ['file' => UploadedFile::fake()->image('private.jpg')]);
        $v = $this->violation($b);

        $this->actingAs($a['officer'])->get('/incidents')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));

        foreach (['/incidents/'.$i->public_id, '/inspections/'.$b['inspection']->public_id, '/violations/'.$v->public_id, '/incidents/'.$i->public_id.'/evidence/'.$i->evidence()->sole()->public_id] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->actingAs($a['manager'])->post('/incidents/'.$i->public_id.'/manage', $this->change($i, 'under_review'))->assertForbidden();

    }

    public function test_operator_view_is_own_only_even_with_shared_park_access(): void
    {
        $n = $this->network();
        $own = $this->incident($n, ['context' => app(FieldLookupService::class)->contexts($n['officer'])->first()['context']]);
        $other = $this->incident($n);

        $u = $this->userWithRole('Transport Operator');
        $u->operators()->attach($n['operator'], ['access_level' => 'manage', 'created_at' => now()]);
        $u->parks()->attach($n['park'], ['access_level' => 'manage', 'created_at' => now()]);

        $this->actingAs($u)->get('/incidents')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $own->public_id));
        $this->get('/incidents/'.$other->public_id)->assertForbidden();
        $this->get('/incidents/'.$own->public_id)->assertOk();
        $this->get('/inspections')->assertForbidden();

    }

    public function test_workflow_records_all_reasons_and_closure_preserves_resolution(): void
    {
        $n = $this->network();
        $i = $this->incident($n);

        foreach (['under_review', 'escalated', 'under_review', 'resolved', 'closed'] as $status) {
            $p = $this->change($i, $status, ['resolution' => 'Synthetic reason for '.$status]);
            app(ManageIncidentAction::class)->execute($n['manager'], $i, $p);
        }
        $i->refresh();
        $this->assertSame('closed', $i->status->value);
        $this->assertSame('Synthetic reason for resolved', $i->resolution);
        $this->assertSame($n['manager']->id, $i->resolved_by);
        $this->assertNotNull($i->resolved_at);
        $this->assertSame(5, Activity::where('description', 'incident_status_changed')->count());
        $this->actingAs($n['manager'])->get('/incidents/'.$i->public_id)->assertInertia(fn (Assert $p) => $p->has('record.history.data', 6)->where('record.history.data.0.reason', 'Synthetic reason for closed'));

    }

    public function test_illegal_stale_and_terminal_transitions_fail_without_overwriting(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $this->actingAs($n['manager'])->post('/incidents/'.$i->public_id.'/manage', $this->change($i, 'resolved'))->assertSessionHasErrors('status');

        $first = $this->change($i, 'under_review');
        app(ManageIncidentAction::class)->execute($n['manager'], $i, $first);

        $this->post('/incidents/'.$i->public_id.'/manage', array_replace($first, ['idempotency_key' => bin2hex(random_bytes(32))]))->assertSessionHasErrors('status');

        app(ManageIncidentAction::class)->execute($n['manager'], $i, $this->change($i, 'resolved'));
        app(ManageIncidentAction::class)->execute($n['manager'], $i, $this->change($i, 'closed'));

        $this->post('/incidents/'.$i->public_id.'/manage', ['expected_status' => 'resolved', 'status' => 'under_review', 'resolution' => 'Cannot reopen a closed report.', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('status');
        $this->assertSame('closed', $i->fresh()->status->value);

    }

    public function test_status_replay_returns_once_and_rejects_changed_reason(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $p = $this->change($i, 'under_review');
        $this->actingAs($n['manager'])->post('/incidents/'.$i->public_id.'/manage', $p)->assertStatus(303);
        $this->post('/incidents/'.$i->public_id.'/manage', $p)->assertStatus(303);
        $this->assertSame(1, Activity::where('description', 'incident_status_changed')->count());
        $this->post('/incidents/'.$i->public_id.'/manage', array_replace($p, ['resolution' => 'Changed reason for replay.']))->assertSessionHasErrors('idempotency_key');

    }

    public function test_violation_derives_inspection_context_and_replays_once(): void
    {
        $n = $this->network();
        $p = ['category' => 'Observed issue', 'description' => 'Synthetic issue for review.', 'idempotency_key' => bin2hex(random_bytes(32)), 'park_id' => 999, 'issued_by' => 999, 'status' => 'resolved'];
        $this->actingAs($n['officer'])->post('/inspections/'.$n['inspection']->public_id.'/violations', $p)->assertStatus(303);
        $this->post('/inspections/'.$n['inspection']->public_id.'/violations', $p)->assertStatus(303);
        $v = Violation::sole();
        $this->assertSame($n['inspection']->id, $v->inspection_id);
        $this->assertSame($n['park']->id, $v->park_id);
        $this->assertSame($n['officer']->id, $v->issued_by);
        $this->assertSame('open', $v->status->value);
        $this->assertSame(1, Activity::where('description', 'violation_recorded')->count());

    }

    public function test_compliant_and_cross_scope_inspections_cannot_create_violations(): void
    {
        $n = $this->network();
        $b = $this->network('B');
        $good = Inspection::create([...$n['inspection']->only(['officer_user_id', 'park_id', 'operator_id', 'driver_id', 'vehicle_id', 'inspection_type', 'occurred_at', 'created_at']), 'inspection_reference' => 'INS-CLEAR', 'result' => 'compliant']);
        $p = ['category' => 'Observed issue', 'description' => 'Synthetic issue for review.', 'idempotency_key' => bin2hex(random_bytes(32))];
        $this->actingAs($n['officer'])->post('/inspections/'.$good->public_id.'/violations', $p)->assertSessionHasErrors('description');
        $this->post('/inspections/'.$b['inspection']->public_id.'/violations', $p)->assertForbidden();
        $this->assertDatabaseCount('violations', 0);

    }

    public function test_violation_resolution_is_final_audited_and_supervised(): void
    {
        $n = $this->network();
        $v = $this->violation($n);
        $p = ['expected_status' => 'open', 'resolution' => 'Reviewed observation and documented outcome.', 'idempotency_key' => bin2hex(random_bytes(32))];
        $this->actingAs($n['officer'])->post('/violations/'.$v->public_id.'/resolve', $p)->assertForbidden();
        $this->actingAs($n['manager'])->post('/violations/'.$v->public_id.'/resolve', $p)->assertStatus(303);
        $this->post('/violations/'.$v->public_id.'/resolve', $p)->assertStatus(303);
        $this->post('/violations/'.$v->public_id.'/resolve', array_replace($p, ['idempotency_key' => bin2hex(random_bytes(32))]))->assertSessionHasErrors('resolution');
        $v->refresh();
        $this->assertSame('resolved', $v->status->value);
        $this->assertSame($p['resolution'], $v->resolution);
        $this->assertSame($n['manager']->id, $v->resolved_by);
        $this->assertSame(1, Activity::where('description', 'violation_resolved')->count());

    }

    public function test_private_evidence_download_and_wrong_subject_protection(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $i = $this->incident($n, ['file' => UploadedFile::fake()->image('private.jpg')]);
        $other = $this->incident($n);
        $m = $i->evidence()->sole();
        Storage::disk('local')->assertExists($m->path);
        $this->assertSame('local', $m->disk);
        $this->assertSame('incident_evidence', $m->category);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($m->path)), $m->file_hash);

        $response = $this->actingAs($n['officer'])->get('/incidents/'.$i->public_id.'/evidence/'.$m->public_id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/incidents/'.$other->public_id.'/evidence/'.$m->public_id)->assertNotFound();
        $this->get('/storage/'.$m->path)->assertForbidden();

    }

    public function test_upload_replay_terminal_rejection_and_read_only_upload_denial(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $i = $this->incident($n);
        $p = ['file' => UploadedFile::fake()->image('private.png'), 'idempotency_key' => bin2hex(random_bytes(32))];
        app(EvidenceService::class)->upload($n['officer'], $i, $p);
        app(EvidenceService::class)->upload($n['officer'], $i, $p);
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());

        $this->actingAs($this->userWithRole('Auditor'))->post('/incidents/'.$i->public_id.'/evidence', $p)->assertForbidden();
        app(ManageIncidentAction::class)->execute($n['manager'], $i, $this->change($i, 'under_review'));
        app(ManageIncidentAction::class)->execute($n['manager'], $i, $this->change($i, 'resolved'));

        app(EvidenceService::class)->upload($n['officer'], $i->fresh(), $p);
        $this->actingAs($n['officer'])->post('/incidents/'.$i->public_id.'/evidence', array_replace($p, ['idempotency_key' => bin2hex(random_bytes(32))]))->assertForbidden();
        $this->assertDatabaseCount('media_attachments', 1);

    }

    public function test_evidence_validation_rejects_scripts_spoofs_and_oversized_files(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $i = $this->incident($n);

        foreach ([UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;
'), $this->spoof(), UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'), UploadedFile::fake()->image('image.jpg')->size(5121)] as $file) {
            $this->actingAs($n['officer'])->post('/incidents/'.$i->public_id.'/evidence', ['file' => $file, 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('file');
        }$this->assertDatabaseCount('media_attachments', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());

    }

    public function test_audit_failure_rolls_back_report_confirmation_metadata_and_file(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $p = $this->payload($n, ['file' => UploadedFile::fake()->image('private.jpg')]);
        Activity::creating(function ($a) {
            if ($a->description === 'evidence_uploaded') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });

        try {
            app(CreateIncidentAction::class)->execute($n['officer'], $p);
            $this->fail('Audit failure must abort.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('media_attachments', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->assertSame(0, Activity::where('description', 'incident_reported')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());

    }

    public function test_scoped_filters_dates_sort_pagination_and_privacy(): void
    {
        $n = $this->network();
        for ($x = 0;
            $x < 17;
            $x++) {
            $this->incident($n, ['occurred_at' => '2025-01-02T03:04']);
        }
        $this->incident($n, ['occurred_at' => '2025-01-03T00:00']);

        $response = $this->actingAs($n['officer'])->get('/incidents?from=2025-01-02&to=2025-01-02&status=reported&category=safety_issue&order=asc');
        $response->assertInertia(fn (Assert $p) => $p->has('records.data', 15)->where('records.total', 17));
        $this->get('/incidents?page=2')->assertInertia(fn (Assert $p) => $p->has('records.data', 3));
        $this->get('/incidents?search=%25')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));

        foreach (['order=sql', 'status=unknown', 'from=bad', 'from=2025-01-03&to=2025-01-01'] as $q) {
            $this->get('/incidents?'.$q)->assertSessionHasErrors();
        }
        $detail = $this->get('/inspections/'.$n['inspection']->public_id);
        foreach (['PRIVATE-PHONE', 'PRIVATE-LICENCE', 'PRIVATE-OWNER', 'file_hash', 'attachable_id'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($detail->viewData('page')['props']));
        }

    }

    public function test_archived_park_remains_readable_and_current_park_geography_controls_incidents(): void
    {
        $n = $this->network();
        $b = $this->network('B');
        $i = $this->incident($n);
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($n['lga'], ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($u)->get('/incidents/'.$i->public_id)->assertOk();
        $n['park']->update(['lga_id' => $b['lga']->id]);
        $this->get('/incidents/'.$i->public_id)->assertForbidden();
        $n['park']->delete();
        $this->actingAs($n['officer'])->get('/incidents/'.$i->public_id)->assertOk();
        $this->get('/inspections/'.$n['inspection']->public_id)->assertOk();

    }

    public function test_approved_schema_restricts_deletion_and_workflows_never_write_finance(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $v = $this->violation($n);
        $this->assertCount(7, Schema::getForeignKeys('violations'));
        $this->assertCount(6, Schema::getForeignKeys('incidents'));
        $this->assertCount(18, Schema::getColumnListing('violations'));
        $this->assertCount(17, Schema::getColumnListing('incidents'));

        foreach ([$i, $v] as $r) {
            try {
                $r->delete();
                $this->fail('History must be retained');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('retained', $e->getMessage());
            }
        }
        foreach (['payments', 'financial_transactions', 'financial_audit_logs', 'refunds', 'financial_adjustments'] as $t) {
            $this->assertDatabaseCount($t, 0);
        }

    }

    public function test_pdf_and_inspection_violation_evidence_are_retained_privately(): void
    {
        Storage::fake('local');
        $n = $this->network();
        $v = $this->violation($n);
        $pdf = UploadedFile::fake()->createWithContent('observation.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        foreach ([$n['inspection'], $v] as $r) {
            $p = ['file' => $pdf, 'idempotency_key' => bin2hex(random_bytes(32))];
            $m = app(EvidenceService::class)->upload($n['officer'], $r, $p);
            $this->assertSame('application/pdf', $m->mime_type);
            $this->assertStringEndsWith('.pdf', $m->path);
            $this->actingAs($n['officer'])->get('/'.strtolower(class_basename($r)).'s/'.$r->public_id.'/evidence/'.$m->public_id)->assertOk();
        } $this->assertDatabaseCount('media_attachments', 2);

    }

    public function test_status_audit_failure_rolls_back_state_and_confirmation(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $p = $this->change($i, 'under_review');
        Activity::creating(function ($a) {
            if ($a->description === 'incident_status_changed') {
                throw new \RuntimeException('Synthetic status audit failure');
            }
        });

        try {
            app(ManageIncidentAction::class)->execute($n['manager'], $i, $p);
            $this->fail('Audit failure must abort');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic status audit failure', $e->getMessage());
        }$this->assertSame('reported', $i->fresh()->status->value);
        $this->assertDatabaseMissing('idempotency_keys', ['idempotency_key' => $p['idempotency_key']]);

    }

    public function test_seed_reset_preserves_resolved_records_audit_and_finances(): void
    {
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.demo_password' => 'SyntheticDemoPass123']);
        $this->seed(DatabaseSeeder::class);
        $i = Incident::sole();
        $v = Violation::sole();
        $manager = User::where('email', 'superadmin@demo.local')->firstOrFail();

        app(ManageIncidentAction::class)->execute($manager, $i, $this->change($i, 'under_review'));
        app(ManageIncidentAction::class)->execute($manager, $i, $this->change($i, 'resolved'));

        app(ResolveViolationAction::class)->execute($manager, $v, ['expected_status' => 'open', 'resolution' => 'Synthetic resolution retained across reset.', 'idempotency_key' => bin2hex(random_bytes(32))]);

        $old = $i->fresh()->getRawOriginal();
        $vold = $v->fresh()->getRawOriginal();
        $audit = Activity::where('description', 'incident_status_changed')->count();
        $financial = DB::table('financial_audit_logs')->pluck('entry_hash', 'id')->all();
        $this->seed(IncidentDemoSeeder::class);
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertSame($old, $i->fresh()->getRawOriginal());
        $this->assertSame($vold, $v->fresh()->getRawOriginal());
        $this->assertSame($audit, Activity::where('description', 'incident_status_changed')->count());
        $this->assertSame($financial, DB::table('financial_audit_logs')->pluck('entry_hash', 'id')->all());
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('violations', 1);

    }

    public function test_guest_inactive_and_password_change_guards_apply_to_incident_writes(): void
    {
        $n = $this->network();
        $this->post('/incidents', $this->payload($n))->assertRedirect('/login');
        $n['officer']->update(['must_change_password' => true]);
        $this->actingAs($n['officer'])->post('/incidents', $this->payload($n))->assertRedirect('/account/security');
        $n['officer']->update(['must_change_password' => false, 'status' => 'inactive']);
        $this->post('/incidents', $this->payload($n))->assertRedirect('/login');
        $this->assertDatabaseCount('incidents', 0);

    }

    public function test_original_incident_and_violation_observations_cannot_be_rewritten(): void
    {
        $n = $this->network();
        $i = $this->incident($n);
        $v = $this->violation($n);
        foreach ([$i, $v] as $r) {
            try {
                $r->update(['description' => 'Changed original observation.']);
                $this->fail('Original observation must be immutable');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    public function test_incident_write_limits_are_actor_bound(): void
    {
        $n = $this->network();
        $this->actingAs($n['officer']);
        for ($x = 0; $x < 30; $x++) {
            $this->post('/incidents', [])->assertSessionHasErrors();
        }
        $this->post('/incidents', [])->assertStatus(429);
        $this->actingAs($n['manager'])->post('/incidents', [])->assertSessionHasErrors();
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_lagos_time_validation_and_day_filters_use_utc_storage(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00', 'UTC'));
        $n = $this->network();
        $i = $this->incident($n, ['occurred_at' => '2026-10-09T10:59']);
        $this->assertSame('2026-10-09 09:59:00', $i->occurred_at->format('Y-m-d H:i:s'));
        $this->actingAs($n['officer'])->post('/incidents', $this->payload($n, ['occurred_at' => '2026-10-09T11:01']))->assertSessionHasErrors('occurred_at');
        $this->get('/incidents/create')->assertInertia(fn (Assert $p) => $p->where('occurred_at', '2026-10-09T11:00'));
        $previous = $this->incident($n, ['occurred_at' => '2026-10-08T23:59']);
        $midnight = $this->incident($n, ['occurred_at' => '2026-10-09T00:00']);
        $this->get('/incidents?from=2026-10-09&to=2026-10-09')->assertInertia(fn (Assert $p) => $p->has('records.data', 2)->where('records.total', 2));
        $this->assertSame('2026-10-08 23:00:00', $midnight->occurred_at->format('Y-m-d H:i:s'));
    }
}
