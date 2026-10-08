<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        if (config('ospm.demo_mode') && ! app()->environment('production')) {
            $this->call(DemoUserSeeder::class);
            $this->call(RegistryDemoSeeder::class);
            $this->call(TransportRevenueDemoSeeder::class);
            $this->call(TicketDemoSeeder::class);
        }
    }
}
