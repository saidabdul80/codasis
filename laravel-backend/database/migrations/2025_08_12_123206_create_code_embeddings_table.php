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
        Schema::create('code_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('codebase_index_id');
            $table->string('chunk_type'); // 'file', 'function', 'class', 'block'
            $table->string('chunk_name')->nullable();
            $table->text('chunk_content');
            $table->integer('start_line')->nullable();
            $table->integer('end_line')->nullable();
            $table->json('embedding'); // Store as JSON for SQLite compatibility
            $table->integer('embedding_model_version')->default(1);
            $table->text('content_hash');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('codebase_index_id')->references('id')->on('codebase_indexes')->onDelete('cascade');
            $table->index(['codebase_index_id', 'chunk_type']);
            $table->index('content_hash');
            $table->index('chunk_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_embeddings');
    }
};
