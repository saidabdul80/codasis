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
        Schema::create('user_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('memory_type'); // 'preference', 'pattern', 'knowledge', 'context'
            $table->string('category')->nullable(); // 'coding_style', 'language_preference', 'workflow', etc.
            $table->string('key');
            $table->json('value');
            $table->text('description')->nullable();
            $table->integer('confidence_score')->default(1); // 1-100
            $table->integer('usage_count')->default(1);
            $table->timestamp('last_accessed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'memory_type']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'key']);
            $table->index('last_accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_memories');
    }
};
