<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_heads', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('code', 50)->unique();
            $t->string('name', 190);
            $t->text('description')->nullable();
            $t->string('frequency', 50);
            $t->string('status', 30);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_heads');
    }
};
