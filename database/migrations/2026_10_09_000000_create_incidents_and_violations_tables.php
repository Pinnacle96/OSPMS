<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('violation_reference', 80)->unique();
            $t->foreignId('inspection_id')->nullable()->constrained()->restrictOnDelete();
            foreach (['park', 'operator', 'driver', 'vehicle'] as $subject) {
                $t->foreignId($subject.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $t->string('category', 80);
            $t->text('description');
            $t->string('status', 30);
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('issued_at');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('resolved_at')->nullable();
            $t->text('resolution')->nullable();
            $t->timestamps();
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('incident_reference', 80)->unique();
            $t->string('category', 80);
            $t->foreignId('park_id')->constrained()->restrictOnDelete();
            $t->foreignId('reporter_user_id')->nullable()->constrained('users')->restrictOnDelete();
            foreach (['operator', 'driver', 'vehicle'] as $subject) {
                $t->foreignId($subject.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $t->text('description');
            $t->string('status', 30);
            $t->dateTime('occurred_at');
            $t->text('resolution')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('violations');
    }
};
