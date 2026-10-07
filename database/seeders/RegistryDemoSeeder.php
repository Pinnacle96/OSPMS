<?php

namespace Database\Seeders;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\ParkRoute;
use App\Domains\Routes\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegistryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            throw new \RuntimeException('Registry demo data requires a non-production demo environment.');
        }
        DB::transaction(function () {
            $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
            $lgas = [];
            $parks = [];
            foreach ([
                ['OSG', 'Osogbo (Demo)', 'Demo Osogbo Central Park', 'active'],
                ['IFC', 'Ife Central (Demo)', 'Demo Ile-Ife Transit Park', 'active'],
                ['ILW', 'Ilesa West (Demo)', 'Demo Ilesa Neighbourhood Park', 'pending'],
            ] as [$code,$name,$parkName,$status]) {
                $lga = Lga::withTrashed()->firstOrCreate(['code' => 'DEMO-'.$code], ['name' => $name, 'status' => 'active', 'administrative_contact_name' => 'Synthetic administrative contact']);
                $lgas[] = $lga;
                $parks[] = Park::withTrashed()->firstOrCreate(['park_code' => 'DEMO-P-'.$code.'-01'], ['lga_id' => $lga->id, 'name' => $parkName, 'address' => 'Synthetic demonstration address; not an official park registration', 'category' => 'Motor park', 'status' => $status, 'activated_at' => $status === 'active' ? now() : null, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            }
            $a = Route::withTrashed()->firstOrCreate(['route_code' => 'DEMO-R-001'], ['origin' => 'Osogbo (Demo)', 'destination' => 'Ile-Ife (Demo)', 'description' => 'Synthetic route for presentation only', 'status' => 'active', 'created_by' => $actor->id]);
            $b = Route::withTrashed()->firstOrCreate(['route_code' => 'DEMO-R-002'], ['origin' => 'Osogbo (Demo)', 'destination' => 'Ilesa (Demo)', 'description' => 'Synthetic route for presentation only', 'status' => 'active', 'created_by' => $actor->id]);
            Route::withTrashed()->firstOrCreate(['route_code' => 'DEMO-R-003'], ['origin' => 'Ilesa (Demo)', 'destination' => 'Ile-Ife (Demo)', 'description' => 'Inactive synthetic route', 'status' => 'inactive', 'created_by' => $actor->id]);
            foreach ([[$parks[0], $a], [$parks[0], $b], [$parks[1], $a]] as [$park,$route]) {
                ParkRoute::firstOrCreate(['park_id' => $park->id, 'route_id' => $route->id], ['status' => 'active', 'created_at' => now()]);
            }
            User::where('email', 'lgaadmin@demo.local')->firstOrFail()->lgas()->syncWithoutDetaching([$lgas[0]->id => ['access_level' => 'manage', 'created_at' => now()]]);
            User::where('email', 'parkmanager@demo.local')->firstOrFail()->parks()->syncWithoutDetaching([$parks[0]->id => ['access_level' => 'manage', 'created_at' => now()]]);
            foreach (['ticketing', 'collection', 'enforcement', 'helpdesk'] as $username) {
                User::where('email', $username.'@demo.local')->firstOrFail()->parks()->syncWithoutDetaching([$parks[0]->id => ['access_level' => 'view', 'created_at' => now()]]);
            }
            activity('system')->causedBy($actor)->withProperties(['lgas' => 3, 'parks' => 3, 'routes' => 3])->log('demo_registry_seeded');
        });
    }
}
