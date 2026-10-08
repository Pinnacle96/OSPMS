<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_configurations', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->foreignId('revenue_head_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3)->default('NGN');
            $t->string('vehicle_type', 50)->nullable();
            foreach (['lga', 'park', 'route'] as $relation) {
                $t->foreignId($relation.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $t->integer('priority')->default(0);
            $t->dateTime('effective_from');
            $t->dateTime('effective_to')->nullable();
            $t->string('status', 30);
            foreach (['approved_by', 'created_by'] as $field) {
                $t->foreignId($field)->nullable()->constrained('users')->nullOnDelete();
            } $t->timestamps();
            $t->index(['revenue_head_id', 'status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_configurations');
    }
};
