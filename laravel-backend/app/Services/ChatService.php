<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ChatService
{
    private AIModelService $aiModelService;

    public function __construct(AIModelService $aiModelService)
    {
        $this->aiModelService = $aiModelService;
    }

    public function sendMessage(string $message, ?string $conversationId = null): array
    {
        $user = Auth::user();
        
        // Get or create conversation
        if ($conversationId) {
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();
        } else {
            $conversation = null;
        }

        if (!$conversation) {
            $conversation = Conversation::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'title' => $this->generateConversationTitle($message),
                'model' => 'deepseek-r1',
            ]);
        }

        // Save user message
        $userMessage = Message::create([
            'id' => Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        // Get conversation context
        $context = $this->buildConversationContext($conversation);

        // Get AI response
        try {
            $aiResponse = $this->aiModelService->askQuestion($message, $context, $conversation->model);
            
            // Save AI message
            $aiMessage = Message::create([
                'id' => Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $aiResponse['response'],
                'metadata' => [
                    'model' => $aiResponse['model'],
                    'usage' => $aiResponse['usage'] ?? null,
                ],
            ]);

            // Update conversation
            $conversation->update([
                'updated_at' => now(),
                'last_message_at' => now(),
            ]);

            return [
                'response' => $aiResponse['response'],
                'conversationId' => $conversation->id,
                'messageId' => $aiMessage->id,
            ];

        } catch (\Exception $e) {
            // Log error and return fallback response
            \Log::error('Chat Service Error', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $fallbackMessage = Message::create([
                'id' => Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error while processing your request. Please try again.',
                'metadata' => ['error' => true],
            ]);

            return [
                'response' => 'I apologize, but I encountered an error while processing your request. Please try again.',
                'conversationId' => $conversation->id,
                'messageId' => $fallbackMessage->id,
                'error' => true,
            ];
        }
    }

    public function getConversation(string $conversationId): ?Conversation
    {
        $user = Auth::user();
        
        return Conversation::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])
        ->where('id', $conversationId)
        ->where('user_id', $user->id)
        ->first();
    }

    public function getUserConversations(int $limit = 20): array
    {
        $user = Auth::user();
        
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('last_message_at', 'desc')
            ->limit($limit)
            ->get();

        return $conversations->map(function ($conversation) {
            return [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
                'created_at' => $conversation->created_at,
                'last_message_at' => $conversation->last_message_at,
                'message_count' => $conversation->messages()->count(),
            ];
        })->toArray();
    }

    public function deleteConversation(string $conversationId): bool
    {
        $user = Auth::user();
        
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return false;
        }

        // Delete all messages first
        $conversation->messages()->delete();
        
        // Delete conversation
        $conversation->delete();

        return true;
    }

    public function updateConversationModel(string $conversationId, string $model): bool
    {
        $user = Auth::user();
        
        if (!$this->aiModelService->isModelAvailable($model)) {
            return false;
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return false;
        }

        $conversation->update(['model' => $model]);
        return true;
    }

    private function buildConversationContext(Conversation $conversation, int $maxMessages = 10): string
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($maxMessages)
            ->get()
            ->reverse();

        $context = "Previous conversation:\n\n";
        
        foreach ($messages as $message) {
            $role = $message->role === 'user' ? 'Human' : 'Assistant';
            $context .= "{$role}: {$message->content}\n\n";
        }

        return $context;
    }

    private function generateConversationTitle(string $firstMessage): string
    {
        // Simple title generation based on first message
        $title = Str::limit($firstMessage, 50);
        
        // Remove common prefixes
        $prefixes = ['how to', 'what is', 'can you', 'please', 'help me'];
        foreach ($prefixes as $prefix) {
            if (Str::startsWith(strtolower($title), $prefix)) {
                $title = Str::substr($title, strlen($prefix));
                break;
            }
        }

        return Str::title(trim($title)) ?: 'New Conversation';
    }

    public function exportConversation(string $conversationId): ?array
    {
        $conversation = $this->getConversation($conversationId);
        
        if (!$conversation) {
            return null;
        }

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'model' => $conversation->model,
            'created_at' => $conversation->created_at,
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                    'metadata' => $message->metadata,
                ];
            })->toArray(),
        ];
    }

    public function searchConversations(string $query, int $limit = 10): array
    {
        $user = Auth::user();
        
        $conversations = Conversation::where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhereHas('messages', function ($mq) use ($query) {
                      $mq->where('content', 'like', "%{$query}%");
                  });
            })
            ->orderBy('last_message_at', 'desc')
            ->limit($limit)
            ->get();

        return $conversations->map(function ($conversation) {
            return [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
                'created_at' => $conversation->created_at,
                'last_message_at' => $conversation->last_message_at,
            ];
        })->toArray();
    }
}
