<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('route_code', 50)->unique();
            $table->string('origin', 190);
            $table->string('destination', 190);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('park_route', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->dateTime('created_at');
            $table->unique(['park_id', 'route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('park_route');
        Schema::dropIfExists('routes');
    }
};
