<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    private ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:10000',
            'conversationId' => 'nullable|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $message = $request->input('message');
            $conversationId = $request->input('conversationId');

            $response = $this->chatService->sendMessage($message, $conversationId);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Chat Message Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'conversation_id' => $request->input('conversationId'),
            ]);

            return response()->json([
                'error' => 'Chat service error',
                'message' => 'Failed to send message. Please try again.'
            ], 500);
        }
    }

    public function getConversation(Request $request, string $conversationId): JsonResponse
    {
        try {
            $conversation = $this->chatService->getConversation($conversationId);

            if (!$conversation) {
                return response()->json([
                    'error' => 'Conversation not found'
                ], 404);
            }

            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'model' => $conversation->model,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'last_message_at' => $conversation->last_message_at,
                    'messages' => $conversation->messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'role' => $message->role,
                            'content' => $message->content,
                            'created_at' => $message->created_at,
                            'metadata' => $message->metadata,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get Conversation Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'conversation_id' => $conversationId,
            ]);

            return response()->json([
                'error' => 'Failed to get conversation'
            ], 500);
        }
    }

    public function getConversations(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 20);
            $conversations = $this->chatService->getUserConversations($limit);

            return response()->json([
                'conversations' => $conversations
            ]);

        } catch (\Exception $e) {
            \Log::error('Get Conversations Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'error' => 'Failed to get conversations'
            ], 500);
        }
    }

    public function deleteConversation(Request $request, string $conversationId): JsonResponse
    {
        try {
            $deleted = $this->chatService->deleteConversation($conversationId);

            if (!$deleted) {
                return response()->json([
                    'error' => 'Conversation not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Conversation deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete Conversation Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'conversation_id' => $conversationId,
            ]);

            return response()->json([
                'error' => 'Failed to delete conversation'
            ], 500);
        }
    }

    public function updateConversationModel(Request $request, string $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model' => 'required|string|in:deepseek-r1,gpt-4,claude-3,gemini-pro',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $model = $request->input('model');
            $updated = $this->chatService->updateConversationModel($conversationId, $model);

            if (!$updated) {
                return response()->json([
                    'error' => 'Conversation not found or model not available'
                ], 404);
            }

            return response()->json([
                'message' => 'Conversation model updated successfully',
                'model' => $model
            ]);

        } catch (\Exception $e) {
            \Log::error('Update Conversation Model Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'conversation_id' => $conversationId,
            ]);

            return response()->json([
                'error' => 'Failed to update conversation model'
            ], 500);
        }
    }

    public function exportConversation(Request $request, string $conversationId): JsonResponse
    {
        try {
            $conversation = $this->chatService->exportConversation($conversationId);

            if (!$conversation) {
                return response()->json([
                    'error' => 'Conversation not found'
                ], 404);
            }

            return response()->json([
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            \Log::error('Export Conversation Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'conversation_id' => $conversationId,
            ]);

            return response()->json([
                'error' => 'Failed to export conversation'
            ], 500);
        }
    }

    public function searchConversations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            $limit = $request->input('limit', 10);

            $conversations = $this->chatService->searchConversations($query, $limit);

            return response()->json([
                'conversations' => $conversations,
                'query' => $query
            ]);

        } catch (\Exception $e) {
            \Log::error('Search Conversations Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'query' => $request->input('query'),
            ]);

            return response()->json([
                'error' => 'Failed to search conversations'
            ], 500);
        }
    }
}
