<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('driver_number', 50)->unique();
            $t->string('first_name', 100);
            $t->string('middle_name', 100)->nullable();
            $t->string('last_name', 100);
            $t->string('phone', 30)->index();
            $t->string('email', 190)->nullable();
            $t->text('residential_address')->nullable();
            $t->string('licence_number', 100)->nullable()->index();
            $t->date('licence_expiry')->nullable();
            $t->string('emergency_contact_name', 150)->nullable();
            $t->string('emergency_contact_phone', 30)->nullable();
            $t->string('next_of_kin', 150)->nullable();
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
        Schema::dropIfExists('drivers');
    }
};
