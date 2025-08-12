<?php

/**
 * AI Model Integration Test Script
 * 
 * This script tests the AI model integration without requiring a full Laravel setup.
 * Run with: php test-ai-integration.php
 */

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AIModelTester
{
    private Client $httpClient;
    private array $modelConfigs;
    private array $results = [];

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        // Load environment variables
        $this->loadEnv();

        $this->modelConfigs = [
            'deepseek-r1' => [
                'url' => $_ENV['DEEPSEEK_API_URL'] ?? 'https://api.deepseek.com/v1/chat/completions',
                'key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
                'model' => 'deepseek-r1',
            ],
            'gpt-4' => [
                'url' => $_ENV['OPENAI_API_URL'] ?? 'https://api.openai.com/v1/chat/completions',
                'key' => $_ENV['OPENAI_API_KEY'] ?? '',
                'model' => 'gpt-4',
            ],
            'claude-3' => [
                'url' => $_ENV['ANTHROPIC_API_URL'] ?? 'https://api.anthropic.com/v1/messages',
                'key' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
                'model' => 'claude-3-sonnet-20240229',
            ],
            'gemini-pro' => [
                'url' => $_ENV['GOOGLE_API_URL'] ?? 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent',
                'key' => $_ENV['GOOGLE_API_KEY'] ?? '',
                'model' => 'gemini-pro',
            ],
        ];
    }

    private function loadEnv()
    {
        $envFile = __DIR__ . '/laravel-backend/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"\'');
                }
            }
        }
    }

    public function testAllModels()
    {
        echo "🤖 Testing AI Model Integration\n";
        echo "================================\n\n";

        $testPrompt = "Write a simple 'Hello, World!' function in Python.";

        foreach ($this->modelConfigs as $modelName => $config) {
            echo "Testing {$modelName}...\n";
            
            if (empty($config['key'])) {
                echo "❌ No API key configured for {$modelName}\n\n";
                $this->results[$modelName] = ['status' => 'no_key', 'error' => 'No API key'];
                continue;
            }

            try {
                $response = $this->testModel($modelName, $testPrompt);
                echo "✅ {$modelName} is working!\n";
                echo "Response preview: " . substr($response, 0, 100) . "...\n\n";
                $this->results[$modelName] = ['status' => 'success', 'response' => $response];
            } catch (Exception $e) {
                echo "❌ {$modelName} failed: " . $e->getMessage() . "\n\n";
                $this->results[$modelName] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        $this->printSummary();
    }

    private function testModel(string $modelName, string $prompt): string
    {
        $config = $this->modelConfigs[$modelName];

        switch ($modelName) {
            case 'deepseek-r1':
            case 'gpt-4':
                return $this->callOpenAICompatible($config, $prompt);
            case 'claude-3':
                return $this->callClaude($config, $prompt);
            case 'gemini-pro':
                return $this->callGemini($config, $prompt);
            default:
                throw new Exception("Unsupported model: {$modelName}");
        }
    }

    private function callOpenAICompatible(array $config, string $prompt): string
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
                'max_tokens' => 500,
                'temperature' => 0.7,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['choices'][0]['message']['content'];
    }

    private function callClaude(array $config, string $prompt): string
    {
        $response = $this->httpClient->post($config['url'], [
            'headers' => [
                'x-api-key' => $config['key'],
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $config['model'],
                'max_tokens' => 500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['content'][0]['text'];
    }

    private function callGemini(array $config, string $prompt): string
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
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    private function printSummary()
    {
        echo "📊 Test Summary\n";
        echo "===============\n\n";

        $successful = 0;
        $total = count($this->results);

        foreach ($this->results as $model => $result) {
            $status = match($result['status']) {
                'success' => '✅ Working',
                'no_key' => '🔑 No API Key',
                'error' => '❌ Error',
                default => '❓ Unknown'
            };

            echo "{$model}: {$status}\n";
            
            if ($result['status'] === 'success') {
                $successful++;
            } elseif ($result['status'] === 'error') {
                echo "   Error: {$result['error']}\n";
            }
        }

        echo "\n";
        echo "Working models: {$successful}/{$total}\n";

        if ($successful === 0) {
            echo "\n⚠️  No AI models are working. Please check your API keys in laravel-backend/.env\n";
            echo "You need at least one working model to use the system.\n";
        } elseif ($successful < $total) {
            echo "\n✨ Some models are working! You can use the system with the working models.\n";
            echo "Configure additional API keys to enable more models.\n";
        } else {
            echo "\n🎉 All models are working perfectly! Your system is ready to use.\n";
        }

        echo "\nNext steps:\n";
        echo "1. Start the Laravel backend: cd laravel-backend && php artisan serve\n";
        echo "2. Install the VSCode extension\n";
        echo "3. Register a user account and get an API token\n";
        echo "4. Configure the VSCode extension with your token\n";
    }

    public function testCodeAnalysis()
    {
        echo "\n🔍 Testing Code Analysis Capabilities\n";
        echo "====================================\n\n";

        $testCode = '
function fibonacci(n) {
    if (n <= 1) return n;
    return fibonacci(n - 1) + fibonacci(n - 2);
}
';

        $analysisPrompt = "Analyze this JavaScript code and suggest improvements:\n\n```javascript{$testCode}```";

        // Test with the first available model
        foreach ($this->results as $modelName => $result) {
            if ($result['status'] === 'success') {
                echo "Testing code analysis with {$modelName}...\n";
                try {
                    $analysis = $this->testModel($modelName, $analysisPrompt);
                    echo "✅ Code analysis working!\n";
                    echo "Analysis preview: " . substr($analysis, 0, 200) . "...\n";
                    return;
                } catch (Exception $e) {
                    echo "❌ Code analysis failed: " . $e->getMessage() . "\n";
                }
                break;
            }
        }

        echo "❌ No working models available for code analysis\n";
    }
}

// Run the tests
try {
    $tester = new AIModelTester();
    $tester->testAllModels();
    $tester->testCodeAnalysis();
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Make sure you're running this from the project root directory.\n";
}
