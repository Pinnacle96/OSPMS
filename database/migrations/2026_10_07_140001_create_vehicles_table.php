<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('vehicle_number', 50)->unique();
            $t->string('registration_number', 30)->unique();
            $t->string('vehicle_type', 50);
            foreach (['make', 'model'] as $field) {
                $t->string($field, 100)->nullable();
            } $t->string('colour', 50)->nullable();
            $t->smallInteger('manufacture_year')->nullable();
            $t->string('owner_name', 190)->nullable();
            $t->string('owner_phone', 30)->nullable();
            $t->date('roadworthiness_expiry')->nullable();
            $t->date('insurance_expiry')->nullable();
            $t->string('status', 30)->default('pending')->index();
            $t->dateTime('registered_at')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
