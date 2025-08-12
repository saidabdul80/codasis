<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIModelService
{
    private Client $httpClient;
    private array $modelConfigs;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        $this->modelConfigs = [
            'deepseek-r1' => [
                'url' => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
                'key' => env('DEEPSEEK_API_KEY'),
                'model' => 'deepseek-r1',
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ],
            'gpt-4' => [
                'url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
                'key' => env('OPENAI_API_KEY'),
                'model' => 'gpt-4',
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ],
            'claude-3' => [
                'url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'),
                'key' => env('ANTHROPIC_API_KEY'),
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ],
            'gemini-pro' => [
                'url' => env('GOOGLE_API_URL', 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent'),
                'key' => env('GOOGLE_API_KEY'),
                'model' => 'gemini-pro',
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ],
        ];
    }

    public function askQuestion(string $prompt, string $context = '', string $model = 'deepseek-r1'): array
    {
        $cacheKey = 'ai_response_' . md5($prompt . $context . $model);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->callAIModel($model, $prompt, $context);
            
            // Cache successful responses for 1 hour
            Cache::put($cacheKey, $response, 3600);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('AI Model Error', [
                'model' => $model,
                'error' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 100) . '...'
            ]);
            
            throw $e;
        }
    }

    public function analyzeCode(string $code, string $language): string
    {
        $prompt = "Analyze the following {$language} code and provide insights about its structure, potential issues, and suggestions for improvement:\n\n```{$language}\n{$code}\n```";
        
        $response = $this->askQuestion($prompt, '', 'deepseek-r1');
        return $response['response'];
    }

    public function explainCode(string $code, string $language): string
    {
        $prompt = "Explain the following {$language} code in detail, including what it does, how it works, and any important concepts:\n\n```{$language}\n{$code}\n```";
        
        $response = $this->askQuestion($prompt, '', 'deepseek-r1');
        return $response['response'];
    }

    public function generateTests(string $code, string $language): string
    {
        $testFrameworks = [
            'javascript' => 'Jest',
            'typescript' => 'Jest',
            'python' => 'pytest',
            'php' => 'PHPUnit',
            'java' => 'JUnit',
            'csharp' => 'NUnit',
        ];

        $framework = $testFrameworks[$language] ?? 'appropriate testing framework';
        
        $prompt = "Generate comprehensive unit tests for the following {$language} code using {$framework}. Include edge cases and error scenarios:\n\n```{$language}\n{$code}\n```";
        
        $response = $this->askQuestion($prompt, '', 'deepseek-r1');
        return $response['response'];
    }

    public function refactorCode(string $code, string $language): string
    {
        $prompt = "Refactor the following {$language} code to improve readability, performance, and maintainability while preserving functionality:\n\n```{$language}\n{$code}\n```\n\nProvide only the refactored code without explanations.";
        
        $response = $this->askQuestion($prompt, '', 'deepseek-r1');
        return $response['response'];
    }

    public function getCompletions(string $prefix, string $context, string $language): array
    {
        $prompt = "Given the following {$language} code context and current line prefix, suggest 3-5 relevant code completions:\n\nContext:\n```{$language}\n{$context}\n```\n\nCurrent line prefix: `{$prefix}`\n\nProvide suggestions in JSON format with 'text', 'description', and 'confidence' fields.";
        
        try {
            $response = $this->askQuestion($prompt, '', 'deepseek-r1');
            $suggestions = json_decode($response['response'], true);
            
            if (!is_array($suggestions)) {
                return [];
            }
            
            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Completion Error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function callAIModel(string $model, string $prompt, string $context = ''): array
    {
        if (!isset($this->modelConfigs[$model])) {
            throw new \InvalidArgumentException("Unsupported model: {$model}");
        }

        $config = $this->modelConfigs[$model];
        
        if (empty($config['key'])) {
            throw new \RuntimeException("API key not configured for model: {$model}");
        }

        $fullPrompt = $context ? "{$context}\n\n{$prompt}" : $prompt;

        switch ($model) {
            case 'deepseek-r1':
            case 'gpt-4':
                return $this->callOpenAICompatible($config, $fullPrompt);
            case 'claude-3':
                return $this->callClaude($config, $fullPrompt);
            case 'gemini-pro':
                return $this->callGemini($config, $fullPrompt);
            default:
                throw new \InvalidArgumentException("Unsupported model: {$model}");
        }
    }

    private function callOpenAICompatible(array $config, string $prompt): array
    {
        $response = $this->httpClient->post($config['url'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['key'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $config['max_tokens'],
                'temperature' => $config['temperature'],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return [
            'response' => $data['choices'][0]['message']['content'],
            'model' => $config['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    private function callClaude(array $config, string $prompt): array
    {
        $response = $this->httpClient->post($config['url'], [
            'headers' => [
                'x-api-key' => $config['key'],
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return [
            'response' => $data['content'][0]['text'],
            'model' => $config['model'],
            'usage' => $data['usage'] ?? null,
        ];
    }

    private function callGemini(array $config, string $prompt): array
    {
        $response = $this->httpClient->post($config['url'] . '?key=' . $config['key'], [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature' => $config['temperature'],
                    'maxOutputTokens' => $config['max_tokens'],
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return [
            'response' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model' => $config['model'],
            'usage' => null,
        ];
    }

    public function getAvailableModels(): array
    {
        return array_keys($this->modelConfigs);
    }

    public function isModelAvailable(string $model): bool
    {
        return isset($this->modelConfigs[$model]) && !empty($this->modelConfigs[$model]['key']);
    }
}
