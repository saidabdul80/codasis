<?php

namespace App\Services;

use App\Models\CodebaseIndex;
use App\Models\CodeEmbedding;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Context Retrieval Service - The core of Augment AI's intelligence
 * 
 * This service implements sophisticated context retrieval algorithms
 * that understand code relationships, dependencies, and semantic meaning
 * to provide highly relevant context for AI interactions.
 */
class ContextRetrievalService
{
    private EmbeddingService $embeddingService;
    private CodeAnalysisService $codeAnalysisService;
    private CodebaseIndexingService $indexingService;

    public function __construct(
        EmbeddingService $embeddingService,
        CodeAnalysisService $codeAnalysisService,
        CodebaseIndexingService $indexingService
    ) {
        $this->embeddingService = $embeddingService;
        $this->codeAnalysisService = $codeAnalysisService;
        $this->indexingService = $indexingService;
    }

    /**
     * Retrieve intelligent context for a given query and current file
     * This is the main method that mimics Augment AI's context engine
     */
    public function retrieveContext(
        int $userId,
        string $query,
        string $currentFile = null,
        array $options = []
    ): array {
        $maxTokens = $options['max_tokens'] ?? 8000;
        $includeTests = $options['include_tests'] ?? false;
        $focusArea = $options['focus_area'] ?? 'general'; // 'general', 'debugging', 'refactoring', 'testing'
        
        $context = [
            'current_file' => null,
            'related_files' => [],
            'dependencies' => [],
            'similar_code' => [],
            'project_context' => [],
            'total_tokens' => 0,
        ];

        try {
            // 1. Analyze current file context
            if ($currentFile) {
                $context['current_file'] = $this->getCurrentFileContext($userId, $currentFile);
            }

            // 2. Find semantically similar code
            $context['similar_code'] = $this->findSimilarCode($userId, $query, $currentFile);

            // 3. Retrieve dependency context
            $context['dependencies'] = $this->getDependencyContext($userId, $currentFile);

            // 4. Get related files based on imports/exports
            $context['related_files'] = $this->getRelatedFiles($userId, $currentFile);

            // 5. Add project-level context
            $context['project_context'] = $this->getProjectContext($userId);

            // 6. Apply focus-specific filtering
            $context = $this->applyFocusFiltering($context, $focusArea, $includeTests);

            // 7. Optimize for token limit
            $context = $this->optimizeForTokenLimit($context, $maxTokens);

            // 8. Rank and prioritize context
            $context = $this->rankAndPrioritizeContext($context, $query, $currentFile);

        } catch (\Exception $e) {
            Log::error('Context retrieval error', [
                'user_id' => $userId,
                'query' => substr($query, 0, 100),
                'current_file' => $currentFile,
                'error' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    /**
     * Get comprehensive context for the current file
     */
    private function getCurrentFileContext(int $userId, string $currentFile): ?array
    {
        $cacheKey = "file_context:{$userId}:" . md5($currentFile);
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $currentFile) {
            $index = CodebaseIndex::where('user_id', $userId)
                ->where('file_path', $currentFile)
                ->first();

            if (!$index) {
                return null;
            }

            return [
                'file_path' => $index->file_path,
                'language' => $index->language,
                'symbols' => json_decode($index->symbols ?? '[]', true),
                'functions' => json_decode($index->functions ?? '[]', true),
                'classes' => json_decode($index->classes ?? '[]', true),
                'imports' => json_decode($index->imports ?? '[]', true),
                'exports' => json_decode($index->exports ?? '[]', true),
                'complexity' => json_decode($index->complexity_metrics ?? '[]', true),
                'metadata' => json_decode($index->metadata ?? '[]', true),
                'content_preview' => $this->getContentPreview($index->content ?? '', 500),
            ];
        });
    }

    /**
     * Find semantically similar code using embeddings
     */
    private function findSimilarCode(int $userId, string $query, string $currentFile = null): array
    {
        try {
            // Generate embedding for the query
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);
            
            // Search for similar code embeddings
            $similarEmbeddings = $this->searchSimilarEmbeddings($userId, $queryEmbedding, $currentFile);
            
            $similarCode = [];
            foreach ($similarEmbeddings as $embedding) {
                $similarCode[] = [
                    'file_path' => $embedding['file_path'],
                    'chunk_type' => $embedding['chunk_type'],
                    'content' => $embedding['content'],
                    'similarity_score' => $embedding['similarity_score'],
                    'context' => $embedding['context'],
                ];
            }

            return array_slice($similarCode, 0, 10); // Top 10 similar pieces
        } catch (\Exception $e) {
            Log::error('Similar code search error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get dependency context for the current file
     */
    private function getDependencyContext(int $userId, string $currentFile = null): array
    {
        if (!$currentFile) {
            return [];
        }

        $dependencies = Cache::get("dependencies:{$userId}:" . md5($currentFile), []);
        $dependencyContext = [];

        foreach ($dependencies as $dep) {
            if ($dep['type'] === 'local') {
                // Get context from local dependencies
                $depFile = $this->resolveLocalDependency($dep['module'], $currentFile);
                if ($depFile) {
                    $depContext = $this->getCurrentFileContext($userId, $depFile);
                    if ($depContext) {
                        $dependencyContext[] = [
                            'type' => 'local_dependency',
                            'module' => $dep['module'],
                            'file_path' => $depFile,
                            'exports' => $depContext['exports'],
                            'key_functions' => array_slice($depContext['functions'], 0, 3),
                        ];
                    }
                }
            }
        }

        return $dependencyContext;
    }

    /**
     * Get files related through imports/exports
     */
    private function getRelatedFiles(int $userId, string $currentFile = null): array
    {
        if (!$currentFile) {
            return [];
        }

        $currentContext = $this->getCurrentFileContext($userId, $currentFile);
        if (!$currentContext) {
            return [];
        }

        $relatedFiles = [];
        $imports = $currentContext['imports'] ?? [];

        // Find files that export what this file imports
        foreach ($imports as $import) {
            $exportingFiles = CodebaseIndex::where('user_id', $userId)
                ->whereJsonContains('exports', ['name' => $import['module']])
                ->limit(3)
                ->get();

            foreach ($exportingFiles as $file) {
                $relatedFiles[] = [
                    'file_path' => $file->file_path,
                    'relationship' => 'imports_from',
                    'symbol' => $import['module'],
                    'language' => $file->language,
                    'key_exports' => array_slice(json_decode($file->exports ?? '[]', true), 0, 5),
                ];
            }
        }

        // Find files that import what this file exports
        $exports = $currentContext['exports'] ?? [];
        foreach ($exports as $export) {
            $importingFiles = CodebaseIndex::where('user_id', $userId)
                ->whereJsonContains('imports', ['module' => $export['name']])
                ->limit(3)
                ->get();

            foreach ($importingFiles as $file) {
                $relatedFiles[] = [
                    'file_path' => $file->file_path,
                    'relationship' => 'imports_to',
                    'symbol' => $export['name'],
                    'language' => $file->language,
                ];
            }
        }

        return array_slice($relatedFiles, 0, 10);
    }

    /**
     * Get high-level project context
     */
    private function getProjectContext(int $userId): array
    {
        $cacheKey = "project_context:{$userId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            $stats = CodebaseIndex::where('user_id', $userId)
                ->selectRaw('
                    language,
                    COUNT(*) as file_count,
                    AVG(line_count) as avg_lines,
                    SUM(line_count) as total_lines
                ')
                ->groupBy('language')
                ->get();

            $projectContext = [
                'languages' => [],
                'total_files' => 0,
                'total_lines' => 0,
                'frameworks' => [],
                'common_patterns' => [],
            ];

            foreach ($stats as $stat) {
                $projectContext['languages'][] = [
                    'language' => $stat->language,
                    'file_count' => $stat->file_count,
                    'avg_lines' => round($stat->avg_lines),
                ];
                $projectContext['total_files'] += $stat->file_count;
                $projectContext['total_lines'] += $stat->total_lines;
            }

            // Get common frameworks
            $frameworks = CodebaseIndex::where('user_id', $userId)
                ->whereNotNull('metadata')
                ->get()
                ->pluck('metadata')
                ->map(fn($m) => json_decode($m, true)['framework'] ?? null)
                ->filter()
                ->countBy()
                ->sortDesc()
                ->take(3);

            $projectContext['frameworks'] = $frameworks->keys()->toArray();

            return $projectContext;
        });
    }

    /**
     * Apply focus-specific filtering to context
     */
    private function applyFocusFiltering(array $context, string $focusArea, bool $includeTests): array
    {
        switch ($focusArea) {
            case 'debugging':
                // Prioritize error handling, logging, and related functions
                $context = $this->prioritizeDebuggingContext($context);
                break;
                
            case 'refactoring':
                // Focus on code structure, patterns, and complexity
                $context = $this->prioritizeRefactoringContext($context);
                break;
                
            case 'testing':
                // Include test files and testable functions
                $includeTests = true;
                $context = $this->prioritizeTestingContext($context);
                break;
        }

        if (!$includeTests) {
            $context = $this->filterOutTestFiles($context);
        }

        return $context;
    }

    /**
     * Optimize context to fit within token limits
     */
    private function optimizeForTokenLimit(array $context, int $maxTokens): array
    {
        $currentTokens = $this->estimateTokenCount($context);
        
        if ($currentTokens <= $maxTokens) {
            $context['total_tokens'] = $currentTokens;
            return $context;
        }

        // Progressively reduce context while maintaining relevance
        $context = $this->reduceContextSize($context, $maxTokens);
        $context['total_tokens'] = $this->estimateTokenCount($context);

        return $context;
    }

    /**
     * Rank and prioritize context based on relevance
     */
    private function rankAndPrioritizeContext(array $context, string $query, string $currentFile = null): array
    {
        // Implement sophisticated ranking algorithm
        // This would include factors like:
        // - Semantic similarity to query
        // - Recency of file modifications
        // - Frequency of file access
        // - Dependency relationships
        // - Code complexity and importance

        return $context;
    }

    // Helper methods would continue here...
    private function getContentPreview(string $content, int $maxLength): string
    {
        return strlen($content) > $maxLength ? substr($content, 0, $maxLength) . '...' : $content;
    }

    private function estimateTokenCount(array $context): int
    {
        // Rough estimation: 1 token ≈ 4 characters
        $content = json_encode($context);
        return intval(strlen($content) / 4);
    }

    private function reduceContextSize(array $context, int $maxTokens): array
    {
        // Implement intelligent context reduction
        // Priority: current_file > dependencies > similar_code > related_files
        
        while ($this->estimateTokenCount($context) > $maxTokens) {
            if (count($context['similar_code']) > 3) {
                array_pop($context['similar_code']);
            } elseif (count($context['related_files']) > 3) {
                array_pop($context['related_files']);
            } elseif (count($context['dependencies']) > 2) {
                array_pop($context['dependencies']);
            } else {
                break;
            }
        }

        return $context;
    }

    private function searchSimilarEmbeddings(int $userId, array $queryEmbedding, string $currentFile = null): array
    {
        // This would implement vector similarity search
        // For now, return empty array as placeholder
        return [];
    }

    private function resolveLocalDependency(string $module, string $currentFile): ?string
    {
        // Implement dependency resolution logic
        return null;
    }

    private function prioritizeDebuggingContext(array $context): array
    {
        // Implement debugging-specific context prioritization
        return $context;
    }

    private function prioritizeRefactoringContext(array $context): array
    {
        // Implement refactoring-specific context prioritization
        return $context;
    }

    private function prioritizeTestingContext(array $context): array
    {
        // Implement testing-specific context prioritization
        return $context;
    }

    private function filterOutTestFiles(array $context): array
    {
        // Filter out test files from context
        foreach (['related_files', 'similar_code'] as $key) {
            if (isset($context[$key])) {
                $context[$key] = array_filter($context[$key], function ($item) {
                    $filePath = $item['file_path'] ?? '';
                    return !preg_match('/\.(test|spec)\.(js|ts|php|py)$/', $filePath) &&
                           !str_contains($filePath, '/tests/') &&
                           !str_contains($filePath, '/test/') &&
                           !str_contains($filePath, '__tests__');
                });
            }
        }
        
        return $context;
    }
}
