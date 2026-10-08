<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $t) {
            $t->id();
            foreach (['driver', 'vehicle', 'operator', 'park'] as $relation) {
                $t->foreignId($relation.'_id')->constrained()->restrictOnDelete();
            }
            $t->foreignId('route_id')->nullable()->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->boolean('is_primary')->default(true);
            $t->string('status', 30);
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            foreach (['driver', 'vehicle', 'operator', 'park'] as $relation) {
                $t->index([$relation.'_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};
