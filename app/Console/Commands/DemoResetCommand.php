<?php

namespace App\Console\Commands;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoResetCommand extends Command
{
    protected $signature = 'ospm:demo-reset';

    protected $description = 'Restore implemented demo identity accounts and roles (non-production only)';

    public function handle(): int
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            $this->error('Refused: demo reset requires demo mode and a non-production environment.');

            return self::FAILURE;
        }
        $password = config('ospm.demo_password');
        if (! is_string($password) || mb_strlen($password) < 12 || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            $this->error('Set a strong DEMO_DEFAULT_PASSWORD before resetting demo accounts.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($password) {
            $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => DemoUserSeeder::class, '--force' => true]);
            foreach (DemoUserSeeder::ACCOUNTS as $username => $role) {
                $user = User::where('email', $username.'@demo.local')->firstOrFail();
                $user->forceFill(['password' => $password, 'status' => UserStatus::Active, 'must_change_password' => false, 'remember_token' => null])->save();
                $user->lgas()->detach();
                $user->parks()->detach();
                $user->operators()->detach();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            activity('system')->log('demo_identity_reset');
        });
        $this->info('Demo identity baseline restored. Audit history preserved. Financial reset is deferred to later milestones.');

        return self::SUCCESS;
    }
}
