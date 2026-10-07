<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_operator_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('access_level', 30);
            $table->dateTime('created_at');
            $table->unique(['user_id', 'operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_operator_access');
    }
};
