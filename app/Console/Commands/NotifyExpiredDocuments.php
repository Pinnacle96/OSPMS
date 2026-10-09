<?php

namespace App\Console\Commands;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\RecordNotificationService;
use App\Domains\Vehicles\Models\Vehicle;
use App\Notifications\DocumentExpiryNotification;
use Illuminate\Console\Command;

class NotifyExpiredDocuments extends Command
{
    protected $signature = 'notifications:expired-documents';

    protected $description = 'Notify record creators of known expired registration documents without changing compliance status.';

    public function handle(): int
    {
        $day = now()->setTimezone('Africa/Lagos')->toDateString();
        foreach ([[Driver::class, 'driver', ['licence_expiry'], 'driver_number'], [Vehicle::class, 'vehicle', ['roadworthiness_expiry', 'insurance_expiry'], 'vehicle_number']] as [$model,$kind,$columns,$reference]) {
            foreach ($columns as $column) {
                $model::whereNotNull('created_by')->whereDate($column, '<', $day)->orderBy('id')->chunkById(100, function ($records) use ($kind, $column, $reference, $day) {
                    foreach ($records as $r) {
                        $r = $r->fresh();
                        if (! $r || ! $r->$column || $r->$column->format('Y-m-d') >= $day) {
                            continue;
                        }app(RecordNotificationService::class)->send(User::find($r->created_by), $r, $kind, 'expiry:'.$column.':'.$r->$column->format('Y-m-d'), $r->$reference, 'A recorded document has expired', DocumentExpiryNotification::class);
                    }
                });
            }
        }
        $this->info('Expired document notifications checked.');

        return self::SUCCESS;
    }
}
