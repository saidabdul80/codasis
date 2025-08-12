<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});

// Protected AI routes
Route::middleware('auth:sanctum')->group(function () {
    
    // AI endpoints
    Route::prefix('ai')->group(function () {
        Route::post('/ask', [AIController::class, 'ask']);
        Route::post('/analyze', [AIController::class, 'analyze']);
        Route::post('/explain', [AIController::class, 'explain']);
        Route::post('/generate-tests', [AIController::class, 'generateTests']);
        Route::post('/refactor', [AIController::class, 'refactor']);
        Route::post('/completions', [AIController::class, 'completions']);
        Route::post('/index-workspace', [AIController::class, 'indexWorkspace']);
        Route::get('/models', [AIController::class, 'models']);
    });

    // Chat endpoints
    Route::prefix('chat')->group(function () {
        Route::post('/', [ChatController::class, 'sendMessage']);
        Route::get('/conversations', [ChatController::class, 'getConversations']);
        Route::get('/conversations/search', [ChatController::class, 'searchConversations']);
        Route::get('/conversations/{conversationId}', [ChatController::class, 'getConversation']);
        Route::delete('/conversations/{conversationId}', [ChatController::class, 'deleteConversation']);
        Route::put('/conversations/{conversationId}/model', [ChatController::class, 'updateConversationModel']);
        Route::get('/conversations/{conversationId}/export', [ChatController::class, 'exportConversation']);
    });

    // User profile and settings
    Route::prefix('user')->group(function () {
        Route::get('/profile', function (Request $request) {
            return response()->json($request->user());
        });
        
        Route::put('/profile', function (Request $request) {
            $user = $request->user();
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
            ]);
            
            $user->update($validated);
            return response()->json($user);
        });
    });

    // Analytics and usage stats
    Route::prefix('analytics')->group(function () {
        Route::get('/usage', function (Request $request) {
            $user = $request->user();
            
            $stats = [
                'total_conversations' => $user->conversations()->count(),
                'total_messages' => $user->conversations()->withCount('messages')->get()->sum('messages_count'),
                'this_month_conversations' => $user->conversations()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'favorite_model' => $user->conversations()
                    ->selectRaw('model, COUNT(*) as count')
                    ->groupBy('model')
                    ->orderByDesc('count')
                    ->first()?->model ?? 'deepseek-r1',
            ];
            
            return response()->json($stats);
        });
    });
});

// Public endpoints (no authentication required)
Route::prefix('public')->group(function () {
    Route::get('/models', [AIController::class, 'models']);
    
    Route::get('/status', function () {
        return response()->json([
            'api_status' => 'operational',
            'models_available' => app(\App\Services\AIModelService::class)->getAvailableModels(),
            'version' => '1.0.0',
            'uptime' => now()->diffInSeconds(app()->bootedAt ?? now()),
        ]);
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found',
        'message' => 'The requested API endpoint does not exist.'
    ], 404);
});
