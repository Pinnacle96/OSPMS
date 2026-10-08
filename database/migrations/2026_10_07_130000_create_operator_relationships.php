<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_park', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operator_id')->constrained()->restrictOnDelete();
            $t->foreignId('park_id')->constrained()->restrictOnDelete();
            $t->string('status', 30);
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('created_at');
            $t->unique(['operator_id', 'park_id']);
        });
        Schema::create('operator_route', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operator_id')->constrained()->restrictOnDelete();
            $t->foreignId('route_id')->constrained()->restrictOnDelete();
            $t->foreignId('park_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status', 30);
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('created_at');
            $t->unique(['operator_id', 'route_id', 'park_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_route');
        Schema::dropIfExists('operator_park');
    }
};
