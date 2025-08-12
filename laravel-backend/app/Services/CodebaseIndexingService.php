<?php

namespace App\Services;

use App\Models\CodebaseIndex;
use App\Models\CodeEmbedding;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class CodebaseIndexingService
{
    private EmbeddingService $embeddingService;
    private CodeAnalysisService $codeAnalysisService;

    public function __construct(
        EmbeddingService $embeddingService,
        CodeAnalysisService $codeAnalysisService
    ) {
        $this->embeddingService = $embeddingService;
        $this->codeAnalysisService = $codeAnalysisService;
    }

    public function indexWorkspace(int $userId, string $workspacePath): array
    {
        $user = User::findOrFail($userId);
        $stats = [
            'files_processed' => 0,
            'files_updated' => 0,
            'files_skipped' => 0,
            'embeddings_created' => 0,
            'errors' => [],
        ];

        try {
            $files = $this->scanWorkspaceFiles($workspacePath);
            
            foreach ($files as $filePath) {
                try {
                    $result = $this->indexFile($user, $workspacePath, $filePath);
                    
                    if ($result['updated']) {
                        $stats['files_updated']++;
                        $stats['embeddings_created'] += $result['embeddings_count'];
                    } else {
                        $stats['files_skipped']++;
                    }
                    
                    $stats['files_processed']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('File indexing error', [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                        'user_id' => $userId,
                    ]);
                }
            }

            // Clean up deleted files
            $this->cleanupDeletedFiles($userId, $workspacePath, $files);

        } catch (\Exception $e) {
            Log::error('Workspace indexing error', [
                'workspace' => $workspacePath,
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }

        return $stats;
    }

    public function indexFile(User $user, string $workspacePath, string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File does not exist: {$filePath}");
        }

        $fileInfo = $this->getFileInfo($workspacePath, $filePath);
        $contentHash = hash('sha256', $fileInfo['content']);

        // Check if file needs reindexing
        $existingIndex = CodebaseIndex::where('user_id', $user->id)
            ->where('file_path', $filePath)
            ->first();

        if ($existingIndex && $existingIndex->content_hash === $contentHash) {
            return ['updated' => false, 'embeddings_count' => 0];
        }

        DB::beginTransaction();
        try {
            // Delete existing embeddings if updating
            if ($existingIndex) {
                $existingIndex->embeddings()->delete();
            }

            // Analyze code structure
            $analysis = $this->codeAnalysisService->analyzeFile($fileInfo['content'], $fileInfo['language']);

            // Create or update codebase index
            $codebaseIndex = CodebaseIndex::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'file_path' => $filePath,
                ],
                [
                    'workspace_path' => $workspacePath,
                    'relative_path' => $fileInfo['relative_path'],
                    'file_name' => $fileInfo['file_name'],
                    'file_extension' => $fileInfo['file_extension'],
                    'language' => $fileInfo['language'],
                    'content' => $fileInfo['content'],
                    'content_hash' => $contentHash,
                    'file_size' => $fileInfo['file_size'],
                    'line_count' => $fileInfo['line_count'],
                    'functions' => $analysis['functions'],
                    'classes' => $analysis['classes'],
                    'imports' => $analysis['imports'],
                    'exports' => $analysis['exports'],
                    'dependencies' => $analysis['dependencies'],
                    'metadata' => $analysis['metadata'],
                    'file_modified_at' => $fileInfo['file_modified_at'],
                    'indexed_at' => now(),
                ]
            );

            // Create embeddings for different code chunks
            $embeddingsCount = $this->createEmbeddings($codebaseIndex, $fileInfo['content'], $analysis);

            DB::commit();

            return ['updated' => true, 'embeddings_count' => $embeddingsCount];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function searchSimilarCode(int $userId, string $query, array $options = []): array
    {
        $workspacePath = $options['workspace_path'] ?? null;
        $language = $options['language'] ?? null;
        $chunkType = $options['chunk_type'] ?? null;
        $limit = $options['limit'] ?? 10;
        $threshold = $options['threshold'] ?? 0.7;

        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);

        // Build query for codebase indexes
        $indexQuery = CodebaseIndex::where('user_id', $userId);
        
        if ($workspacePath) {
            $indexQuery->where('workspace_path', $workspacePath);
        }
        
        if ($language) {
            $indexQuery->where('language', $language);
        }

        $codebaseIndexIds = $indexQuery->pluck('id');

        // Search embeddings
        $embeddingQuery = CodeEmbedding::whereIn('codebase_index_id', $codebaseIndexIds);
        
        if ($chunkType) {
            $embeddingQuery->where('chunk_type', $chunkType);
        }

        $embeddings = $embeddingQuery->with('codebaseIndex')->get();

        // Calculate similarities and filter
        $results = [];
        foreach ($embeddings as $embedding) {
            $similarity = $embedding->calculateSimilarity($queryEmbedding);
            
            if ($similarity >= $threshold) {
                $results[] = [
                    'similarity' => $similarity,
                    'chunk_type' => $embedding->chunk_type,
                    'chunk_name' => $embedding->chunk_name,
                    'chunk_content' => $embedding->chunk_content,
                    'start_line' => $embedding->start_line,
                    'end_line' => $embedding->end_line,
                    'file_path' => $embedding->codebaseIndex->file_path,
                    'relative_path' => $embedding->codebaseIndex->relative_path,
                    'language' => $embedding->codebaseIndex->language,
                    'metadata' => $embedding->metadata,
                ];
            }
        }

        // Sort by similarity and limit results
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($results, 0, $limit);
    }

    public function getContextualCode(int $userId, string $currentFile, int $contextLines = 50): array
    {
        $codebaseIndex = CodebaseIndex::where('user_id', $userId)
            ->where('file_path', $currentFile)
            ->first();

        if (!$codebaseIndex) {
            return [];
        }

        $context = [
            'current_file' => [
                'path' => $codebaseIndex->relative_path,
                'language' => $codebaseIndex->language,
                'functions' => $codebaseIndex->function_names,
                'classes' => $codebaseIndex->class_names,
                'imports' => $codebaseIndex->import_names,
            ],
            'related_files' => [],
            'dependencies' => [],
        ];

        // Find related files based on imports/dependencies
        if (!empty($codebaseIndex->dependencies)) {
            $relatedFiles = CodebaseIndex::where('user_id', $userId)
                ->where('workspace_path', $codebaseIndex->workspace_path)
                ->where('id', '!=', $codebaseIndex->id)
                ->get()
                ->filter(function ($file) use ($codebaseIndex) {
                    $fileDeps = $file->dependencies ?? [];
                    $currentDeps = $codebaseIndex->dependencies ?? [];
                    
                    return !empty(array_intersect($fileDeps, $currentDeps));
                })
                ->take(10);

            foreach ($relatedFiles as $file) {
                $context['related_files'][] = [
                    'path' => $file->relative_path,
                    'language' => $file->language,
                    'functions' => $file->function_names,
                    'classes' => $file->class_names,
                ];
            }
        }

        return $context;
    }

    private function scanWorkspaceFiles(string $workspacePath): array
    {
        $files = [];
        $excludePatterns = [
            '/node_modules/',
            '/vendor/',
            '/.git/',
            '/dist/',
            '/build/',
            '/coverage/',
            '/.vscode/',
            '/.idea/',
            '/tmp/',
            '/temp/',
        ];

        $allowedExtensions = [
            'js', 'jsx', 'ts', 'tsx', 'py', 'php', 'java', 'cs', 'cpp', 'c', 'h', 'hpp',
            'go', 'rs', 'rb', 'swift', 'kt', 'scala', 'clj', 'hs', 'ml', 'fs', 'vb',
            'sql', 'html', 'css', 'scss', 'sass', 'less', 'xml', 'json', 'yaml', 'yml',
        ];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($workspacePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getPathname();
                    $relativePath = str_replace($workspacePath, '', $filePath);
                    
                    // Skip excluded directories
                    $skip = false;
                    foreach ($excludePatterns as $pattern) {
                        if (strpos($relativePath, $pattern) !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    
                    if ($skip) {
                        continue;
                    }

                    // Check file extension
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, $allowedExtensions)) {
                        $files[] = $filePath;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error scanning workspace', [
                'workspace' => $workspacePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $files;
    }

    private function getFileInfo(string $workspacePath, string $filePath): array
    {
        $content = file_get_contents($filePath);
        $fileInfo = pathinfo($filePath);
        $relativePath = str_replace($workspacePath, '', $filePath);
        
        return [
            'content' => $content,
            'relative_path' => ltrim($relativePath, '/\\'),
            'file_name' => $fileInfo['basename'],
            'file_extension' => strtolower($fileInfo['extension'] ?? ''),
            'language' => $this->detectLanguage($fileInfo['extension'] ?? ''),
            'file_size' => strlen($content),
            'line_count' => substr_count($content, "\n") + 1,
            'file_modified_at' => \Carbon\Carbon::createFromTimestamp(filemtime($filePath)),
        ];
    }

    private function detectLanguage(string $extension): ?string
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
        ];

        return $languageMap[strtolower($extension)] ?? null;
    }

    private function createEmbeddings(CodebaseIndex $codebaseIndex, string $content, array $analysis): int
    {
        $embeddingsCount = 0;

        // Create file-level embedding
        $fileEmbedding = $this->embeddingService->generateEmbedding($content);
        CodeEmbedding::create([
            'codebase_index_id' => $codebaseIndex->id,
            'chunk_type' => 'file',
            'chunk_name' => $codebaseIndex->file_name,
            'chunk_content' => substr($content, 0, 1000), // First 1000 chars for preview
            'embedding' => $fileEmbedding,
            'content_hash' => hash('sha256', $content),
        ]);
        $embeddingsCount++;

        // Create function-level embeddings
        foreach ($analysis['functions'] as $function) {
            if (!empty($function['content'])) {
                $functionEmbedding = $this->embeddingService->generateEmbedding($function['content']);
                CodeEmbedding::create([
                    'codebase_index_id' => $codebaseIndex->id,
                    'chunk_type' => 'function',
                    'chunk_name' => $function['name'],
                    'chunk_content' => $function['content'],
                    'start_line' => $function['start_line'] ?? null,
                    'end_line' => $function['end_line'] ?? null,
                    'embedding' => $functionEmbedding,
                    'content_hash' => hash('sha256', $function['content']),
                    'metadata' => [
                        'parameters' => $function['parameters'] ?? [],
                        'return_type' => $function['return_type'] ?? null,
                    ],
                ]);
                $embeddingsCount++;
            }
        }

        // Create class-level embeddings
        foreach ($analysis['classes'] as $class) {
            if (!empty($class['content'])) {
                $classEmbedding = $this->embeddingService->generateEmbedding($class['content']);
                CodeEmbedding::create([
                    'codebase_index_id' => $codebaseIndex->id,
                    'chunk_type' => 'class',
                    'chunk_name' => $class['name'],
                    'chunk_content' => $class['content'],
                    'start_line' => $class['start_line'] ?? null,
                    'end_line' => $class['end_line'] ?? null,
                    'embedding' => $classEmbedding,
                    'content_hash' => hash('sha256', $class['content']),
                    'metadata' => [
                        'methods' => $class['methods'] ?? [],
                        'properties' => $class['properties'] ?? [],
                        'extends' => $class['extends'] ?? null,
                        'implements' => $class['implements'] ?? [],
                    ],
                ]);
                $embeddingsCount++;
            }
        }

        return $embeddingsCount;
    }

    private function cleanupDeletedFiles(int $userId, string $workspacePath, array $currentFiles): void
    {
        $existingIndexes = CodebaseIndex::where('user_id', $userId)
            ->where('workspace_path', $workspacePath)
            ->get();

        foreach ($existingIndexes as $index) {
            if (!in_array($index->file_path, $currentFiles)) {
                $index->embeddings()->delete();
                $index->delete();
            }
        }
    }
}
