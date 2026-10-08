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
        Schema::create('custom_provider_models', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model_id');

            // Nullable: the label is derived from the model id when the endpoint
            // gives no display name, so it never has to be stored, but an
            // endpoint that does supply one keeps it.
            $table->string('label')->nullable();
            $table->timestamps();

            // Sync re-runs the same list often, and a duplicate would surface as
            // the same model twice in the picker.
            $table->unique(['provider', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_provider_models');
    }
};
