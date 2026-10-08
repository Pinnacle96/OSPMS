<?php

namespace Database\Seeders;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Operators\Models\OperatorPark;
use App\Domains\Operators\Models\OperatorRoute;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransportRevenueDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            throw new \RuntimeException('Synthetic registry data requires non-production demo mode.');
        }
        DB::transaction(function () {
            $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
            foreach (['OSG', 'IFC'] as $index => $code) {
                $park = Park::where('park_code', 'DEMO-P-'.$code.'-01')->first();
                if (! $park) {
                    continue;
                }
                $route = $park->routes()->where('routes.status', 'active')->wherePivot('status', 'active')->first();
                if (! $route) {
                    continue;
                }
                $op = Operator::withTrashed()->firstOrCreate(['operator_number' => 'DEMO-OPR-'.$code], ['name' => 'Demo '.$code.' Transport Services', 'contact_person' => 'Synthetic contact', 'phone' => '00000000000', 'email' => strtolower($code).'@operator.demo.local', 'address' => 'Synthetic address for demonstration only', 'status' => 'approved', 'registered_at' => now(), 'approved_at' => now(), 'approved_by' => $actor->id, 'created_by' => $actor->id]);
                OperatorPark::firstOrCreate(['operator_id' => $op->id, 'park_id' => $park->id], ['status' => 'active', 'approved_at' => now(), 'created_at' => now()]);
                OperatorRoute::firstOrCreate(['operator_id' => $op->id, 'park_id' => $park->id, 'route_id' => $route->id], ['status' => 'active', 'approved_at' => now(), 'created_at' => now()]);
                for ($i = 1; $i <= 3; $i++) {
                    $driver = Driver::withTrashed()->firstOrCreate(['driver_number' => 'DEMO-DRV-'.$code.'-'.$i], ['first_name' => 'Demo', 'last_name' => $code.' Driver '.$i, 'phone' => '00000000000', 'licence_number' => 'SYNTHETIC-'.$code.'-'.$i, 'licence_expiry' => '2027-12-31', 'status' => $i === 3 ? 'pending' : 'active', 'registered_at' => now(), 'approved_at' => $i === 3 ? null : now(), 'approved_by' => $i === 3 ? null : $actor->id, 'created_by' => $actor->id]);
                    $vehicle = Vehicle::withTrashed()->firstOrCreate(['vehicle_number' => 'DEMO-VEH-'.$code.'-'.$i], ['registration_number' => 'DEMO-'.$code.'-00'.$i, 'vehicle_type' => $i === 1 ? 'bus' : 'minibus', 'make' => 'Synthetic', 'model' => 'Demo vehicle', 'roadworthiness_expiry' => '2027-12-31', 'insurance_expiry' => '2027-12-31', 'status' => $i === 3 ? 'pending' : 'active', 'registered_at' => now(), 'approved_at' => $i === 3 ? null : now(), 'approved_by' => $i === 3 ? null : $actor->id, 'created_by' => $actor->id]);
                    // Never recreate an ended assignment during repeated seeding.
                    if ($i < 3 && ! $driver->trashed() && ! $vehicle->trashed() && ! $op->trashed()) {
                        DriverAssignment::firstOrCreate(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $op->id, 'park_id' => $park->id, 'route_id' => $route->id], ['starts_at' => '2026-10-01 08:00:00', 'is_primary' => true, 'status' => 'active', 'assigned_by' => $actor->id]);
                    }
                }
                if ($index === 0) {
                    User::where('email', 'operator@demo.local')->firstOrFail()->operators()->syncWithoutDetaching([$op->id => ['access_level' => 'view', 'created_at' => now()]]);
                }
            }
            $head = RevenueHead::withTrashed()->firstOrCreate(['code' => 'DEMO-DPT'], ['name' => 'Demo daily park ticket', 'description' => 'Synthetic fee, not an approved Government charge', 'frequency' => 'daily', 'status' => 'active', 'created_by' => $actor->id]);
            FeeConfiguration::firstOrCreate(['revenue_head_id' => $head->id, 'effective_from' => '2026-01-01 00:00:00', 'park_id' => null, 'vehicle_type' => null], ['amount' => '500.00', 'currency' => 'NGN', 'priority' => 0, 'status' => 'active', 'approved_by' => $actor->id, 'created_by' => $actor->id]);
        });
    }
}
