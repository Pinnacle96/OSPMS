<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parks', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('park_code', 50)->unique();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->string('name', 190)->index();
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 30)->default('pending');
            $table->dateTime('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lga_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parks');
    }
};
