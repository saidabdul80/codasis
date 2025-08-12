<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AgentTask extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'description',
        'original_request',
        'status',
        'priority',
        'subtasks',
        'execution_plan',
        'progress',
        'results',
        'context',
        'metadata',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'subtasks' => 'array',
        'execution_plan' => 'array',
        'progress' => 'array',
        'results' => 'array',
        'context' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        $progress = $this->progress ?? [];
        $totalSteps = count($this->subtasks ?? []);
        
        if ($totalSteps === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        $completedSteps = count(array_filter($progress, function ($step) {
            return ($step['status'] ?? '') === 'completed';
        }));

        return (int) round(($completedSteps / $totalSteps) * 100);
    }

    public function getCurrentStepAttribute(): ?array
    {
        $progress = $this->progress ?? [];
        
        foreach ($progress as $step) {
            if (($step['status'] ?? '') === 'in_progress') {
                return $step;
            }
        }

        return null;
    }

    public function getEstimatedTimeRemainingAttribute(): ?int
    {
        if ($this->status === 'completed' || $this->status === 'failed') {
            return 0;
        }

        $progress = $this->progress ?? [];
        $totalSteps = count($this->subtasks ?? []);
        $completedSteps = count(array_filter($progress, function ($step) {
            return ($step['status'] ?? '') === 'completed';
        }));

        if ($completedSteps === 0 || !$this->started_at) {
            return null;
        }

        $elapsedMinutes = $this->started_at->diffInMinutes(now());
        $averageTimePerStep = $elapsedMinutes / $completedSteps;
        $remainingSteps = $totalSteps - $completedSteps;

        return (int) round($averageTimePerStep * $remainingSteps);
    }

    public function markStepCompleted(int $stepIndex, array $result = []): void
    {
        $progress = $this->progress ?? [];
        
        if (isset($progress[$stepIndex])) {
            $progress[$stepIndex]['status'] = 'completed';
            $progress[$stepIndex]['completed_at'] = now()->toISOString();
            $progress[$stepIndex]['result'] = $result;
        }

        $this->update(['progress' => $progress]);

        // Check if all steps are completed
        $allCompleted = true;
        foreach ($progress as $step) {
            if (($step['status'] ?? '') !== 'completed') {
                $allCompleted = false;
                break;
            }
        }

        if ($allCompleted) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    public function markStepFailed(int $stepIndex, string $error): void
    {
        $progress = $this->progress ?? [];
        
        if (isset($progress[$stepIndex])) {
            $progress[$stepIndex]['status'] = 'failed';
            $progress[$stepIndex]['error'] = $error;
            $progress[$stepIndex]['failed_at'] = now()->toISOString();
        }

        $this->update([
            'progress' => $progress,
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'planning', 'executing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
