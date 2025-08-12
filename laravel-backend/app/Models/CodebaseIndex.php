<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CodebaseIndex extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'workspace_path',
        'file_path',
        'relative_path',
        'file_name',
        'file_extension',
        'language',
        'content',
        'content_hash',
        'file_size',
        'line_count',
        'functions',
        'classes',
        'imports',
        'exports',
        'dependencies',
        'metadata',
        'file_modified_at',
        'indexed_at',
    ];

    protected $casts = [
        'functions' => 'array',
        'classes' => 'array',
        'imports' => 'array',
        'exports' => 'array',
        'dependencies' => 'array',
        'metadata' => 'array',
        'file_modified_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(CodeEmbedding::class);
    }

    public function getFunctionNamesAttribute(): array
    {
        return array_column($this->functions ?? [], 'name');
    }

    public function getClassNamesAttribute(): array
    {
        return array_column($this->classes ?? [], 'name');
    }

    public function getImportNamesAttribute(): array
    {
        return array_column($this->imports ?? [], 'name');
    }

    public function isOutdated(): bool
    {
        if (!file_exists($this->file_path)) {
            return true;
        }

        $fileModifiedTime = filemtime($this->file_path);
        return $fileModifiedTime > $this->file_modified_at->timestamp;
    }

    public function getLanguageFromExtension(): ?string
    {
        $languageMap = [
            'js' => 'javascript',
            'jsx' => 'javascript',
            'ts' => 'typescript',
            'tsx' => 'typescript',
            'py' => 'python',
            'php' => 'php',
            'java' => 'java',
            'cs' => 'csharp',
            'cpp' => 'cpp',
            'c' => 'c',
            'h' => 'c',
            'hpp' => 'cpp',
            'go' => 'go',
            'rs' => 'rust',
            'rb' => 'ruby',
            'swift' => 'swift',
            'kt' => 'kotlin',
            'scala' => 'scala',
            'clj' => 'clojure',
            'hs' => 'haskell',
            'ml' => 'ocaml',
            'fs' => 'fsharp',
            'vb' => 'vbnet',
            'sql' => 'sql',
            'html' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'sass' => 'sass',
            'less' => 'less',
            'xml' => 'xml',
            'json' => 'json',
            'yaml' => 'yaml',
            'yml' => 'yaml',
            'toml' => 'toml',
            'ini' => 'ini',
            'cfg' => 'ini',
            'conf' => 'ini',
            'sh' => 'bash',
            'bash' => 'bash',
            'zsh' => 'zsh',
            'fish' => 'fish',
            'ps1' => 'powershell',
            'bat' => 'batch',
            'cmd' => 'batch',
            'dockerfile' => 'dockerfile',
            'md' => 'markdown',
            'tex' => 'latex',
            'r' => 'r',
            'R' => 'r',
            'jl' => 'julia',
            'dart' => 'dart',
            'elm' => 'elm',
            'ex' => 'elixir',
            'exs' => 'elixir',
            'erl' => 'erlang',
            'hrl' => 'erlang',
            'lua' => 'lua',
            'pl' => 'perl',
            'pm' => 'perl',
            'tcl' => 'tcl',
            'vim' => 'vim',
            'asm' => 'assembly',
            's' => 'assembly',
        ];

        return $languageMap[$this->file_extension] ?? null;
    }

    public function scopeByWorkspace($query, string $workspacePath)
    {
        return $query->where('workspace_path', $workspacePath);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeByExtension($query, string $extension)
    {
        return $query->where('file_extension', $extension);
    }

    public function scopeOutdated($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('file_modified_at > indexed_at')
              ->orWhereNull('indexed_at');
        });
    }

    public function scopeRecentlyModified($query, int $hours = 24)
    {
        return $query->where('file_modified_at', '>=', now()->subHours($hours));
    }
}
