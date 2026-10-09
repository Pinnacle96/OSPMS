<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('complaint_reference', 80)->unique();
            $t->string('complainant_name', 190);
            $t->string('complainant_phone', 30)->nullable();
            $t->string('complainant_email', 190)->nullable();
            $t->string('category', 80);
            foreach (['park_id' => 'parks', 'operator_id' => 'operators', 'driver_id' => 'drivers', 'vehicle_id' => 'vehicles', 'assigned_to' => 'users', 'submitted_by' => 'users'] as $c => $table) {
                $t->foreignId($c)->nullable()->constrained($table)->restrictOnDelete();
            }
            $t->text('description');
            $t->string('source', 30);
            $t->string('status', 30)->index();
            $t->text('resolution')->nullable();
            $t->dateTime('resolved_at')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });
        Schema::create('complaint_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('complaint_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('note');
            $t->boolean('is_internal')->default(true);
            $t->dateTime('created_at');
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->json('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('complaint_notes');
        Schema::dropIfExists('complaints');
    }
};
