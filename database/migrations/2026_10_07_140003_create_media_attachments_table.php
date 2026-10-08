<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('attachable_type', 190);
            $t->unsignedBigInteger('attachable_id');
            $t->index(['attachable_type', 'attachable_id']);
            $t->string('category', 50);
            $t->string('disk', 50);
            $t->string('path', 500);
            $t->string('original_name', 255)->nullable();
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->string('file_hash', 128)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
