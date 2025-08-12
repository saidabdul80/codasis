<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CodeEmbedding extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'codebase_index_id',
        'chunk_type',
        'chunk_name',
        'chunk_content',
        'start_line',
        'end_line',
        'embedding',
        'embedding_model_version',
        'content_hash',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function codebaseIndex(): BelongsTo
    {
        return $this->belongsTo(CodebaseIndex::class);
    }

    public function calculateSimilarity(array $queryEmbedding): float
    {
        $embedding = $this->embedding;
        
        if (empty($embedding) || empty($queryEmbedding)) {
            return 0.0;
        }

        // Cosine similarity calculation
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($embedding), count($queryEmbedding));

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $embedding[$i] * $queryEmbedding[$i];
            $normA += $embedding[$i] * $embedding[$i];
            $normB += $queryEmbedding[$i] * $queryEmbedding[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    public function scopeByChunkType($query, string $chunkType)
    {
        return $query->where('chunk_type', $chunkType);
    }

    public function scopeByCodebaseIndex($query, string $codebaseIndexId)
    {
        return $query->where('codebase_index_id', $codebaseIndexId);
    }

    public function scopeSimilarTo($query, array $queryEmbedding, float $threshold = 0.7)
    {
        // This is a simplified version - in production, you'd use vector database operations
        return $query->get()->filter(function ($embedding) use ($queryEmbedding, $threshold) {
            return $embedding->calculateSimilarity($queryEmbedding) >= $threshold;
        });
    }
}
