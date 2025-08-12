<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIModelService;
use App\Services\ContextRetrievalService;
use App\Services\CodebaseIndexingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    private AIModelService $aiModelService;
    private ContextRetrievalService $contextRetrievalService;
    private CodebaseIndexingService $indexingService;

    public function __construct(
        AIModelService $aiModelService,
        ContextRetrievalService $contextRetrievalService,
        CodebaseIndexingService $indexingService
    ) {
        $this->aiModelService = $aiModelService;
        $this->contextRetrievalService = $contextRetrievalService;
        $this->indexingService = $indexingService;
    }

    public function ask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|max:10000',
            'context' => 'nullable|string|max:50000',
            'current_file' => 'nullable|string|max:500',
            'workspace_path' => 'nullable|string|max:500',
            'model' => 'nullable|string|in:deepseek-r1,gpt-4,claude-3,gemini-pro',
            'language' => 'nullable|string|max:50',
            'maxTokens' => 'nullable|integer|min:1|max:8000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'focus_area' => 'nullable|string|in:general,debugging,refactoring,testing',
            'include_tests' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();
            $model = $request->input('model', 'deepseek-r1');
            $prompt = $request->input('prompt');
            $currentFile = $request->input('current_file');
            $workspacePath = $request->input('workspace_path');
            $focusArea = $request->input('focus_area', 'general');
            $includeTests = $request->input('include_tests', false);

            if (!$this->aiModelService->isModelAvailable($model)) {
                return response()->json([
                    'error' => 'Model not available',
                    'message' => "The model '{$model}' is not configured or available."
                ], 400);
            }

            // Use intelligent context retrieval instead of basic context
            $intelligentContext = '';
            if ($currentFile || $workspacePath) {
                $contextData = $this->contextRetrievalService->retrieveContext(
                    $userId,
                    $prompt,
                    $currentFile,
                    [
                        'focus_area' => $focusArea,
                        'include_tests' => $includeTests,
                        'max_tokens' => 6000, // Leave room for prompt and response
                    ]
                );

                $intelligentContext = $this->formatContextForAI($contextData);
            }

            // Fallback to provided context if no intelligent context available
            $finalContext = $intelligentContext ?: $request->input('context', '');

            $response = $this->aiModelService->askQuestion($prompt, $finalContext, $model);

            // Add context metadata to response
            $response['context_used'] = [
                'type' => $intelligentContext ? 'intelligent' : 'provided',
                'token_count' => $contextData['total_tokens'] ?? 0,
                'files_referenced' => count($contextData['related_files'] ?? []),
                'dependencies_included' => count($contextData['dependencies'] ?? []),
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('AI Ask Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'model' => $request->input('model'),
                'current_file' => $request->input('current_file'),
            ]);

            return response()->json([
                'error' => 'AI service error',
                'message' => 'Failed to get AI response. Please try again.'
            ], 500);
        }
    }

    /**
     * Index a workspace for intelligent context retrieval
     */
    public function indexWorkspace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workspace_path' => 'required|string|max:500',
            'force_reindex' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();
            $workspacePath = $request->input('workspace_path');
            $forceReindex = $request->input('force_reindex', false);

            // Check if workspace exists
            if (!is_dir($workspacePath)) {
                return response()->json([
                    'error' => 'Workspace not found',
                    'message' => 'The specified workspace path does not exist.'
                ], 404);
            }

            $stats = $this->indexingService->indexWorkspace($userId, $workspacePath);

            return response()->json([
                'message' => 'Workspace indexed successfully',
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            \Log::error('Workspace Indexing Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'workspace_path' => $request->input('workspace_path'),
            ]);

            return response()->json([
                'error' => 'Indexing failed',
                'message' => 'Failed to index workspace. Please try again.'
            ], 500);
        }
    }

    /**
     * Format context data for AI consumption
     */
    private function formatContextForAI(array $contextData): string
    {
        $formatted = [];

        // Current file context
        if ($contextData['current_file']) {
            $file = $contextData['current_file'];
            $formatted[] = "## Current File: {$file['file_path']}";
            $formatted[] = "Language: {$file['language']}";

            if (!empty($file['functions'])) {
                $formatted[] = "### Functions:";
                foreach (array_slice($file['functions'], 0, 5) as $func) {
                    $formatted[] = "- {$func['name']} (line {$func['line']})";
                }
            }

            if (!empty($file['classes'])) {
                $formatted[] = "### Classes:";
                foreach (array_slice($file['classes'], 0, 3) as $class) {
                    $formatted[] = "- {$class['name']} (line {$class['line']})";
                }
            }

            if (!empty($file['imports'])) {
                $formatted[] = "### Imports:";
                foreach (array_slice($file['imports'], 0, 5) as $import) {
                    $formatted[] = "- {$import['module']}";
                }
            }
        }

        // Related files
        if (!empty($contextData['related_files'])) {
            $formatted[] = "\n## Related Files:";
            foreach (array_slice($contextData['related_files'], 0, 3) as $related) {
                $formatted[] = "- {$related['file_path']} ({$related['relationship']})";
            }
        }

        // Dependencies
        if (!empty($contextData['dependencies'])) {
            $formatted[] = "\n## Dependencies:";
            foreach (array_slice($contextData['dependencies'], 0, 3) as $dep) {
                $formatted[] = "- {$dep['module']} from {$dep['file_path']}";
            }
        }

        // Similar code
        if (!empty($contextData['similar_code'])) {
            $formatted[] = "\n## Similar Code:";
            foreach (array_slice($contextData['similar_code'], 0, 2) as $similar) {
                $formatted[] = "### From {$similar['file_path']}:";
                $formatted[] = "```{$similar['chunk_type']}";
                $formatted[] = substr($similar['content'], 0, 300) . '...';
                $formatted[] = "```";
            }
        }

        // Project context
        if (!empty($contextData['project_context'])) {
            $project = $contextData['project_context'];
            $formatted[] = "\n## Project Context:";
            $formatted[] = "Total files: {$project['total_files']}";
            $formatted[] = "Languages: " . implode(', ', array_column($project['languages'], 'language'));
            if (!empty($project['frameworks'])) {
                $formatted[] = "Frameworks: " . implode(', ', $project['frameworks']);
            }
        }

        return implode("\n", $formatted);
    }

    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100000',
            'language' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            $language = $request->input('language');

            $analysis = $this->aiModelService->analyzeCode($code, $language);

            return response()->json([
                'analysis' => $analysis,
                'language' => $language,
                'code_length' => strlen($code),
            ]);

        } catch (\Exception $e) {
            \Log::error('Code Analysis Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'error' => 'Analysis failed',
                'message' => 'Failed to analyze code. Please try again.'
            ], 500);
        }
    }

    public function explain(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50000',
            'language' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            $language = $request->input('language');

            $explanation = $this->aiModelService->explainCode($code, $language);

            return response()->json([
                'explanation' => $explanation,
                'language' => $language,
            ]);

        } catch (\Exception $e) {
            \Log::error('Code Explanation Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'error' => 'Explanation failed',
                'message' => 'Failed to explain code. Please try again.'
            ], 500);
        }
    }

    public function generateTests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50000',
            'language' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            $language = $request->input('language');

            $tests = $this->aiModelService->generateTests($code, $language);

            return response()->json([
                'tests' => $tests,
                'language' => $language,
            ]);

        } catch (\Exception $e) {
            \Log::error('Test Generation Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'error' => 'Test generation failed',
                'message' => 'Failed to generate tests. Please try again.'
            ], 500);
        }
    }

    public function refactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50000',
            'language' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            $language = $request->input('language');

            $refactoredCode = $this->aiModelService->refactorCode($code, $language);

            return response()->json([
                'refactoredCode' => $refactoredCode,
                'language' => $language,
                'originalLength' => strlen($code),
                'refactoredLength' => strlen($refactoredCode),
            ]);

        } catch (\Exception $e) {
            \Log::error('Code Refactoring Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'error' => 'Refactoring failed',
                'message' => 'Failed to refactor code. Please try again.'
            ], 500);
        }
    }

    public function completions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prefix' => 'required|string|max:1000',
            'context' => 'required|string|max:10000',
            'language' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $prefix = $request->input('prefix');
            $context = $request->input('context');
            $language = $request->input('language');

            $suggestions = $this->aiModelService->getCompletions($prefix, $context, $language);

            return response()->json([
                'suggestions' => $suggestions,
                'language' => $language,
            ]);

        } catch (\Exception $e) {
            \Log::error('Code Completion Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'suggestions' => [],
                'error' => 'Completion failed',
            ]);
        }
    }

    public function models(): JsonResponse
    {
        try {
            $models = $this->aiModelService->getAvailableModels();
            $modelStatus = [];

            foreach ($models as $model) {
                $modelStatus[$model] = $this->aiModelService->isModelAvailable($model);
            }

            return response()->json([
                'models' => $models,
                'status' => $modelStatus,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get model information',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
