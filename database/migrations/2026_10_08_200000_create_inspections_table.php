<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('inspection_reference', 80)->unique();
            $t->foreignId('officer_user_id')->constrained('users')->restrictOnDelete();
            foreach (['park', 'operator', 'driver', 'vehicle', 'ticket'] as $subject) {
                $t->foreignId($subject.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $t->string('inspection_type', 50);
            $t->string('result', 50);
            $t->text('notes')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->dateTime('occurred_at');
            $t->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
