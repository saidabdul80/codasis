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
        Schema::create('codebase_indexes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('workspace_path');
            $table->string('file_path');
            $table->string('relative_path');
            $table->string('file_name');
            $table->string('file_extension');
            $table->string('language')->nullable();
            $table->longText('content');
            $table->text('content_hash');
            $table->integer('file_size');
            $table->integer('line_count');
            $table->json('functions')->nullable();
            $table->json('classes')->nullable();
            $table->json('imports')->nullable();
            $table->json('exports')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('file_modified_at');
            $table->timestamp('indexed_at');
            $table->timestamps();

            $table->index(['user_id', 'workspace_path']);
            $table->index(['user_id', 'language']);
            $table->index(['user_id', 'file_extension']);
            $table->index('content_hash');
            $table->index('indexed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codebase_indexes');
    }
};
