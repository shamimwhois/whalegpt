<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('kind', 24)->default('text');
            $table->longText('content')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['conversation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
