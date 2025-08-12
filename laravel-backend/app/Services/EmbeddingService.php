<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private Client $httpClient;
    private string $embeddingModel;
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        // Use OpenAI embeddings by default, fallback to local implementation
        $this->embeddingModel = env('EMBEDDING_MODEL', 'text-embedding-3-small');
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->apiUrl = env('OPENAI_EMBEDDING_URL', 'https://api.openai.com/v1/embeddings');
    }

    public function generateEmbedding(string $text): array
    {
        // Clean and prepare text
        $text = $this->preprocessText($text);
        
        // Check cache first
        $cacheKey = 'embedding_' . hash('sha256', $text . $this->embeddingModel);
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $embedding = $this->callEmbeddingAPI($text);
            
            // Cache for 24 hours
            Cache::put($cacheKey, $embedding, 86400);
            
            return $embedding;
        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);
            
            // Fallback to simple hash-based embedding
            return $this->generateFallbackEmbedding($text);
        }
    }

    public function generateBatchEmbeddings(array $texts): array
    {
        $embeddings = [];
        
        // Process in batches to avoid API limits
        $batchSize = 100;
        $batches = array_chunk($texts, $batchSize);
        
        foreach ($batches as $batch) {
            try {
                $batchEmbeddings = $this->callBatchEmbeddingAPI($batch);
                $embeddings = array_merge($embeddings, $batchEmbeddings);
            } catch (\Exception $e) {
                Log::error('Batch embedding generation failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]);
                
                // Fallback to individual processing
                foreach ($batch as $text) {
                    $embeddings[] = $this->generateEmbedding($text);
                }
            }
        }
        
        return $embeddings;
    }

    public function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        if (empty($embedding1) || empty($embedding2)) {
            return 0.0;
        }

        // Ensure same dimensions
        $length = min(count($embedding1), count($embedding2));
        
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $normA += $embedding1[$i] * $embedding1[$i];
            $normB += $embedding2[$i] * $embedding2[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    public function findMostSimilar(array $queryEmbedding, array $candidateEmbeddings, int $topK = 5): array
    {
        $similarities = [];
        
        foreach ($candidateEmbeddings as $index => $embedding) {
            $similarity = $this->calculateSimilarity($queryEmbedding, $embedding['vector']);
            $similarities[] = [
                'index' => $index,
                'similarity' => $similarity,
                'data' => $embedding['data'] ?? null,
            ];
        }

        // Sort by similarity (descending)
        usort($similarities, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similarities, 0, $topK);
    }

    private function callEmbeddingAPI(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $response = $this->httpClient->post($this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->embeddingModel,
                'input' => $text,
                'encoding_format' => 'float',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($data['data'][0]['embedding'])) {
            throw new \RuntimeException('Invalid embedding response format');
        }

        return $data['data'][0]['embedding'];
    }

    private function callBatchEmbeddingAPI(array $texts): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $response = $this->httpClient->post($this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->embeddingModel,
                'input' => $texts,
                'encoding_format' => 'float',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($data['data'])) {
            throw new \RuntimeException('Invalid batch embedding response format');
        }

        $embeddings = [];
        foreach ($data['data'] as $item) {
            $embeddings[] = $item['embedding'];
        }

        return $embeddings;
    }

    private function preprocessText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim to reasonable length (8000 chars for embeddings)
        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000);
        }
        
        return trim($text);
    }

    private function generateFallbackEmbedding(string $text): array
    {
        // Simple fallback embedding based on text characteristics
        // This is not as good as real embeddings but provides basic functionality
        
        $embedding = array_fill(0, 384, 0.0); // 384-dimensional vector
        
        // Basic text features
        $length = strlen($text);
        $wordCount = str_word_count($text);
        $lineCount = substr_count($text, "\n") + 1;
        
        // Character frequency analysis
        $charFreq = array_count_values(str_split(strtolower($text)));
        
        // Programming language indicators
        $jsIndicators = ['function', 'const', 'let', 'var', '=>', 'console.log'];
        $pythonIndicators = ['def ', 'import ', 'class ', 'if __name__', 'print('];
        $phpIndicators = ['<?php', 'function ', 'class ', '$', '->'];
        
        // Fill embedding with features
        $embedding[0] = min($length / 1000, 1.0);
        $embedding[1] = min($wordCount / 100, 1.0);
        $embedding[2] = min($lineCount / 50, 1.0);
        
        // Language indicators
        $embedding[10] = $this->countIndicators($text, $jsIndicators) / 10;
        $embedding[11] = $this->countIndicators($text, $pythonIndicators) / 10;
        $embedding[12] = $this->countIndicators($text, $phpIndicators) / 10;
        
        // Character frequency features
        $commonChars = ['a', 'e', 'i', 'o', 'u', 't', 'n', 's', 'r', 'l'];
        foreach ($commonChars as $i => $char) {
            $embedding[20 + $i] = ($charFreq[$char] ?? 0) / max($length, 1);
        }
        
        // Code structure indicators
        $embedding[50] = substr_count($text, '{') / max($length, 1) * 100;
        $embedding[51] = substr_count($text, '(') / max($length, 1) * 100;
        $embedding[52] = substr_count($text, '[') / max($length, 1) * 100;
        $embedding[53] = substr_count($text, '"') / max($length, 1) * 100;
        $embedding[54] = substr_count($text, "'") / max($length, 1) * 100;
        
        // Add some randomness based on text hash for uniqueness
        $hash = hash('sha256', $text);
        for ($i = 100; $i < 200; $i++) {
            $embedding[$i] = (hexdec(substr($hash, ($i - 100) % 64, 2)) / 255) - 0.5;
        }
        
        // Normalize the embedding
        $norm = sqrt(array_sum(array_map(function ($x) { return $x * $x; }, $embedding)));
        if ($norm > 0) {
            $embedding = array_map(function ($x) use ($norm) { return $x / $norm; }, $embedding);
        }
        
        return $embedding;
    }

    private function countIndicators(string $text, array $indicators): int
    {
        $count = 0;
        $lowerText = strtolower($text);
        
        foreach ($indicators as $indicator) {
            $count += substr_count($lowerText, strtolower($indicator));
        }
        
        return $count;
    }

    public function getEmbeddingDimensions(): int
    {
        // Return dimensions based on model
        $dimensions = [
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'text-embedding-ada-002' => 1536,
        ];

        return $dimensions[$this->embeddingModel] ?? 384; // Fallback dimension
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
