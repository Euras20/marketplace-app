<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_admin_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('data_file_path');
            $table->string('image_archive_path')->nullable();
            $table->string('status')->default('uploaded'); // uploaded, validating, ready, processing, completed, completed_with_errors, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku')->nullable();
            $table->string('status')->default('pending'); // pending, valid, invalid, processed, failed
            $table->json('error_codes')->nullable();
            $table->json('error_messages')->nullable();
            $table->foreignId('created_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'import_job_id']);
            $table->index(['import_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_jobs');
    }
};
