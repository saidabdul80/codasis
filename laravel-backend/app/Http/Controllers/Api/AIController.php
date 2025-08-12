<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIModelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    private AIModelService $aiModelService;

    public function __construct(AIModelService $aiModelService)
    {
        $this->aiModelService = $aiModelService;
    }

    public function ask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|max:10000',
            'context' => 'nullable|string|max:50000',
            'model' => 'nullable|string|in:deepseek-r1,gpt-4,claude-3,gemini-pro',
            'language' => 'nullable|string|max:50',
            'maxTokens' => 'nullable|integer|min:1|max:8000',
            'temperature' => 'nullable|numeric|min:0|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $model = $request->input('model', 'deepseek-r1');
            $prompt = $request->input('prompt');
            $context = $request->input('context', '');

            if (!$this->aiModelService->isModelAvailable($model)) {
                return response()->json([
                    'error' => 'Model not available',
                    'message' => "The model '{$model}' is not configured or available."
                ], 400);
            }

            $response = $this->aiModelService->askQuestion($prompt, $context, $model);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('AI Ask Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'model' => $request->input('model'),
            ]);

            return response()->json([
                'error' => 'AI service error',
                'message' => 'Failed to get AI response. Please try again.'
            ], 500);
        }
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
