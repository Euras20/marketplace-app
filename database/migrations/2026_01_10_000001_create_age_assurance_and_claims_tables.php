<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_assurance_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type'); // guest_token_hash or customer
            $table->string('subject_identifier'); // hash or customer public_id
            $table->string('provider')->nullable();
            $table->string('result')->default('pending'); // pending, eligible, ineligible
            $table->string('assertion_hash')->nullable(); // hash of trusted assertion
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('evidence_metadata')->nullable(); // minimal, no raw identity docs
            $table->timestamps();

            $table->index(['store_id', 'subject_identifier']);
            $table->index(['store_id', 'result']);
        });

        Schema::create('guest_order_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('claim_token_hash');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'claim_token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_order_claims');
        Schema::dropIfExists('age_assurance_results');
    }
};
