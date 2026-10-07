<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->unique();
            $table->string('administrative_contact_name', 150)->nullable();
            $table->string('administrative_contact_phone', 30)->nullable();
            $table->string('administrative_contact_email', 190)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgas');
    }
};
