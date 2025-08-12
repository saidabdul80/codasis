<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserMemory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'memory_type',
        'category',
        'key',
        'value',
        'description',
        'confidence_score',
        'usage_count',
        'last_accessed_at',
        'metadata',
    ];

    protected $casts = [
        'value' => 'array',
        'metadata' => 'array',
        'last_accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function updateConfidence(int $score): void
    {
        // Weighted average of current confidence and new score
        $currentWeight = $this->usage_count;
        $newWeight = 1;
        $totalWeight = $currentWeight + $newWeight;
        
        $newConfidence = (($this->confidence_score * $currentWeight) + ($score * $newWeight)) / $totalWeight;
        
        $this->update(['confidence_score' => round($newConfidence)]);
    }

    public function isRelevant(): bool
    {
        return $this->confidence_score >= 50 && $this->usage_count >= 2;
    }

    public function isStale(int $days = 30): bool
    {
        return $this->last_accessed_at->lt(now()->subDays($days));
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('memory_type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeRelevant($query, int $minConfidence = 50, int $minUsage = 2)
    {
        return $query->where('confidence_score', '>=', $minConfidence)
                    ->where('usage_count', '>=', $minUsage);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeStale($query, int $days = 30)
    {
        return $query->where('last_accessed_at', '<', now()->subDays($days));
    }

    public function scopeHighConfidence($query, int $threshold = 80)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    public function scopeFrequentlyUsed($query, int $threshold = 10)
    {
        return $query->where('usage_count', '>=', $threshold);
    }

    // Static methods for common memory operations
    public static function rememberPreference(int $userId, string $category, string $key, $value, string $description = null): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'memory_type' => 'preference',
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? $value : ['value' => $value],
                'description' => $description,
                'confidence_score' => 90,
                'usage_count' => 1,
                'last_accessed_at' => now(),
            ]
        );
    }

    public static function rememberPattern(int $userId, string $category, string $key, $value, string $description = null): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'memory_type' => 'pattern',
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? $value : ['pattern' => $value],
                'description' => $description,
                'confidence_score' => 70,
                'usage_count' => 1,
                'last_accessed_at' => now(),
            ]
        );
    }

    public static function rememberKnowledge(int $userId, string $category, string $key, $value, string $description = null): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'memory_type' => 'knowledge',
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? $value : ['knowledge' => $value],
                'description' => $description,
                'confidence_score' => 80,
                'usage_count' => 1,
                'last_accessed_at' => now(),
            ]
        );
    }

    public static function rememberContext(int $userId, string $category, string $key, $value, string $description = null): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'memory_type' => 'context',
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? $value : ['context' => $value],
                'description' => $description,
                'confidence_score' => 60,
                'usage_count' => 1,
                'last_accessed_at' => now(),
            ]
        );
    }

    public static function getUserPreferences(int $userId, string $category = null): array
    {
        $query = self::where('user_id', $userId)
                    ->where('memory_type', 'preference')
                    ->relevant();

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get()->mapWithKeys(function ($memory) {
            return [$memory->key => $memory->value];
        })->toArray();
    }

    public static function getUserPatterns(int $userId, string $category = null): array
    {
        $query = self::where('user_id', $userId)
                    ->where('memory_type', 'pattern')
                    ->relevant();

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get()->mapWithKeys(function ($memory) {
            return [$memory->key => $memory->value];
        })->toArray();
    }
}
