<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('none');
            $table->string('provider_id')->nullable();
            $table->integer('amount_in_cents')->default(0);
            $table->string('currency', 3)->default('USD');

            // Stored so a failed charge can be explained without asking the
            // provider again.
            $table->string('status')->default('pending');
            $table->string('description')->nullable();
            $table->timestamps();

            // The provider's own id is unique, so a webhook delivered twice
            // updates one row rather than recording two payments.
            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
