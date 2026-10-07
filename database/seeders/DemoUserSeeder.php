<?php

namespace Database\Seeders;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const ACCOUNTS = [
        'superadmin' => 'Super Administrator', 'stateadmin' => 'State Administrator', 'executive' => 'Executive Viewer',
        'finance' => 'Finance Administrator', 'revenue' => 'Revenue Officer', 'auditor' => 'Auditor', 'lgaadmin' => 'LGA Administrator',
        'parkmanager' => 'Park Manager', 'ticketing' => 'Ticketing Officer', 'collection' => 'Collection Agent',
        'enforcement' => 'Enforcement Officer', 'helpdesk' => 'Help Desk Officer', 'operator' => 'Transport Operator',
    ];

    public function run(): void
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            throw new \RuntimeException('Demo accounts require a non-production demo environment.');
        }
        $password = config('ospm.demo_password');
        if (! is_string($password) || mb_strlen($password) < 12 || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            throw new \RuntimeException('Set DEMO_DEFAULT_PASSWORD to at least 12 characters with mixed case and a number.');
        }
        foreach (self::ACCOUNTS as $username => $role) {
            $user = User::firstOrCreate(['email' => $username.'@demo.local'], ['username' => $username, 'name' => 'Demo '.$role, 'password' => $password, 'status' => UserStatus::Active]);
            $user->syncRoles([$role]);
        }
    }
}
