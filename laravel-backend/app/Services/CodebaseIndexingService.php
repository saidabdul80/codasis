<?php

namespace App\Services;

use App\Models\CodebaseIndex;
use App\Models\CodeEmbedding;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

class CodebaseIndexingService
{
    private EmbeddingService $embeddingService;
    private CodeAnalysisService $codeAnalysisService;

    private array $supportedExtensions = [
        'php', 'js', 'jsx', 'ts', 'tsx', 'py', 'java', 'kt', 'swift',
        'go', 'rs', 'cpp', 'c', 'cs', 'rb', 'vue', 'html', 'css',
        'scss', 'json', 'xml', 'yaml', 'yml', 'md', 'sql'
    ];

    private array $excludePatterns = [
        'node_modules', 'vendor', '.git', 'dist', 'build', 'coverage',
        '.next', '.nuxt', '__pycache__', '.pytest_cache', 'target',
        'bin', 'obj', '.vs', '.vscode/settings.json'
    ];

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

    public function extractContextualInformation(string $content, array $analysis): array
    {
        $contextualInfo = [
            'framework' => null,
            'patterns' => [],
            'api_endpoints' => [],
            'database_queries' => [],
            'security_patterns' => [],
            'performance_patterns' => [],
        ];

        // Detect framework patterns
        $contextualInfo['framework'] = $this->detectFrameworkFromContent($content, $analysis['language']);

        // Extract API endpoints
        $contextualInfo['api_endpoints'] = $this->extractApiEndpoints($content, $analysis['language']);

        // Extract database queries
        $contextualInfo['database_queries'] = $this->extractDatabaseQueries($content, $analysis['language']);

        // Detect design patterns
        $contextualInfo['patterns'] = $this->detectDesignPatterns($content, $analysis['language']);

        // Security pattern detection
        $contextualInfo['security_patterns'] = $this->detectSecurityPatterns($content);

        // Performance pattern detection
        $contextualInfo['performance_patterns'] = $this->detectPerformancePatterns($content, $analysis['language']);

        return $contextualInfo;
    }

    private function detectFrameworkFromContent(string $content, string $language): ?string
    {
        $frameworkPatterns = [
            'php' => [
                'Laravel' => ['Illuminate\\', 'Artisan::', 'Route::', 'Schema::', 'Eloquent'],
                'Symfony' => ['Symfony\\', 'use Doctrine\\', '@Route', '@Entity'],
                'CodeIgniter' => ['$this->load->', 'CI_Controller', '$this->db->'],
                'Zend' => ['Zend\\', 'Laminas\\'],
            ],
            'javascript' => [
                'React' => ['import React', 'useState', 'useEffect', 'jsx', 'React.Component'],
                'Vue' => ['Vue.component', 'new Vue', 'v-if', 'v-for', '@click'],
                'Angular' => ['@Component', '@Injectable', 'ngOnInit', 'Angular'],
                'Express' => ['express()', 'app.get', 'app.post', 'req.body'],
                'Next.js' => ['next/', 'getStaticProps', 'getServerSideProps'],
            ],
            'typescript' => [
                'Angular' => ['@Component', '@Injectable', 'ngOnInit'],
                'NestJS' => ['@Controller', '@Injectable', '@Get', '@Post'],
                'React' => ['React.FC', 'useState', 'useEffect'],
            ],
            'python' => [
                'Django' => ['django.', 'models.Model', 'HttpResponse', 'render'],
                'Flask' => ['from flask', '@app.route', 'Flask(__name__)'],
                'FastAPI' => ['from fastapi', '@app.get', '@app.post', 'FastAPI()'],
            ],
        ];

        if (!isset($frameworkPatterns[$language])) {
            return null;
        }

        foreach ($frameworkPatterns[$language] as $framework => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    return $framework;
                }
            }
        }

        return null;
    }

    private function extractApiEndpoints(string $content, string $language): array
    {
        $endpoints = [];

        $patterns = [
            'php' => [
                '/Route::(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
                '/@Route\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            ],
            'javascript' => [
                '/app\.(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
                '/router\.(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            ],
            'typescript' => [
                '/@(Get|Post|Put|Delete|Patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            ],
            'python' => [
                '/@app\.route\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
                '/@(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            ],
        ];

        if (isset($patterns[$language])) {
            foreach ($patterns[$language] as $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $endpoints[] = [
                            'method' => strtoupper($match[1] ?? 'GET'),
                            'path' => $match[2] ?? $match[1],
                            'line' => substr_count(substr($content, 0, strpos($content, $match[0])), "\n") + 1,
                        ];
                    }
                }
            }
        }

        return $endpoints;
    }

    private function extractDatabaseQueries(string $content, string $language): array
    {
        $queries = [];

        $patterns = [
            'php' => [
                '/DB::(select|insert|update|delete|raw)\s*\(/i',
                '/\$this->db->(get|insert|update|delete|query)\s*\(/i',
                '/(SELECT|INSERT|UPDATE|DELETE)\s+.+?(FROM|INTO|SET|WHERE)/is',
            ],
            'javascript' => [
                '/\.(find|findOne|insertOne|updateOne|deleteOne|aggregate)\s*\(/i',
                '/(SELECT|INSERT|UPDATE|DELETE)\s+.+?(FROM|INTO|SET|WHERE)/is',
            ],
            'python' => [
                '/\.(filter|get|create|update|delete|raw)\s*\(/i',
                '/(SELECT|INSERT|UPDATE|DELETE)\s+.+?(FROM|INTO|SET|WHERE)/is',
            ],
        ];

        if (isset($patterns[$language])) {
            foreach ($patterns[$language] as $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $queries[] = [
                            'query' => trim($match[0]),
                            'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                        ];
                    }
                }
            }
        }

        return $queries;
    }

    private function detectDesignPatterns(string $content, string $language): array
    {
        $patterns = [];

        // Common design patterns to detect
        $patternIndicators = [
            'Singleton' => ['private static \$instance', 'getInstance()', 'private function __construct'],
            'Factory' => ['Factory', 'create()', 'make()'],
            'Observer' => ['Observer', 'notify()', 'attach()', 'detach()'],
            'Strategy' => ['Strategy', 'execute()', 'setStrategy()'],
            'Decorator' => ['Decorator', 'decorate()', 'wrap()'],
            'Repository' => ['Repository', 'find()', 'save()', 'delete()'],
            'Service' => ['Service', 'handle()', 'process()'],
            'Builder' => ['Builder', 'build()', 'with()'],
        ];

        foreach ($patternIndicators as $pattern => $indicators) {
            $score = 0;
            foreach ($indicators as $indicator) {
                if (str_contains($content, $indicator)) {
                    $score++;
                }
            }

            if ($score >= 2) {
                $patterns[] = [
                    'pattern' => $pattern,
                    'confidence' => min(100, ($score / count($indicators)) * 100),
                ];
            }
        }

        return $patterns;
    }

    private function detectSecurityPatterns(string $content): array
    {
        $securityPatterns = [];

        $securityIndicators = [
            'SQL Injection Risk' => ['$_GET', '$_POST', '$_REQUEST', 'query(', 'exec('],
            'XSS Risk' => ['echo $_', 'print $_', 'innerHTML', 'document.write'],
            'CSRF Protection' => ['csrf_token', '@csrf', 'csrf_field'],
            'Authentication' => ['Auth::', 'login()', 'authenticate()', 'password_verify'],
            'Authorization' => ['authorize()', 'can()', 'cannot()', 'middleware'],
            'Encryption' => ['encrypt()', 'decrypt()', 'hash()', 'bcrypt()', 'password_hash'],
            'Input Validation' => ['validate()', 'sanitize()', 'filter_var', 'htmlspecialchars'],
        ];

        foreach ($securityIndicators as $pattern => $indicators) {
            $count = 0;
            foreach ($indicators as $indicator) {
                $count += substr_count($content, $indicator);
            }

            if ($count > 0) {
                $securityPatterns[] = [
                    'pattern' => $pattern,
                    'occurrences' => $count,
                ];
            }
        }

        return $securityPatterns;
    }

    private function detectPerformancePatterns(string $content, string $language): array
    {
        $performancePatterns = [];

        $performanceIndicators = [
            'Caching' => ['Cache::', 'cache()', 'remember()', 'Redis::', 'Memcached'],
            'Database Optimization' => ['with()', 'eager loading', 'chunk()', 'cursor()'],
            'Async Operations' => ['async', 'await', 'Promise', 'setTimeout', 'setInterval'],
            'Memory Management' => ['unset()', 'gc_collect_cycles()', 'memory_get_usage()'],
            'Query Optimization' => ['select()', 'where()', 'orderBy()', 'limit()', 'offset()'],
        ];

        foreach ($performanceIndicators as $pattern => $indicators) {
            $count = 0;
            foreach ($indicators as $indicator) {
                $count += substr_count($content, $indicator);
            }

            if ($count > 0) {
                $performancePatterns[] = [
                    'pattern' => $pattern,
                    'occurrences' => $count,
                ];
            }
        }

        return $performancePatterns;
    }

    public function analyzeProjectStructure(string $workspacePath): array
    {
        $structure = [
            'type' => 'unknown',
            'framework' => null,
            'language' => null,
            'package_managers' => [],
            'config_files' => [],
            'entry_points' => [],
        ];

        // Check for common project files
        $projectFiles = [
            'package.json' => ['type' => 'javascript', 'manager' => 'npm'],
            'composer.json' => ['type' => 'php', 'manager' => 'composer'],
            'requirements.txt' => ['type' => 'python', 'manager' => 'pip'],
            'Pipfile' => ['type' => 'python', 'manager' => 'pipenv'],
            'pyproject.toml' => ['type' => 'python', 'manager' => 'poetry'],
            'Cargo.toml' => ['type' => 'rust', 'manager' => 'cargo'],
            'go.mod' => ['type' => 'go', 'manager' => 'go'],
            'pom.xml' => ['type' => 'java', 'manager' => 'maven'],
            'build.gradle' => ['type' => 'java', 'manager' => 'gradle'],
            'Gemfile' => ['type' => 'ruby', 'manager' => 'bundler'],
        ];

        foreach ($projectFiles as $file => $info) {
            if (file_exists($workspacePath . '/' . $file)) {
                $structure['type'] = $info['type'];
                $structure['package_managers'][] = $info['manager'];
                $structure['config_files'][] = $file;
            }
        }

        // Detect framework
        $structure['framework'] = $this->detectFramework($workspacePath, $structure['type']);

        return $structure;
    }

    public function filterRelevantFiles(array $files): array
    {
        return array_filter($files, function ($file) {
            // Check if file should be excluded
            foreach ($this->excludePatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    return false;
                }
            }

            // Check if file extension is supported
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return in_array($extension, $this->supportedExtensions);
        });
    }

    public function buildDependencyGraph(int $userId, array $dependencies): void
    {
        // Group dependencies by file
        $dependencyMap = [];
        foreach ($dependencies as $dep) {
            $file = $dep['file'] ?? 'unknown';
            if (!isset($dependencyMap[$file])) {
                $dependencyMap[$file] = [];
            }
            $dependencyMap[$file][] = $dep;
        }

        // Store dependency relationships
        foreach ($dependencyMap as $file => $deps) {
            Cache::put("dependencies:{$userId}:{$file}", $deps, 3600);
        }
    }

    private function detectFramework(string $workspacePath, string $type): ?string
    {
        $frameworkIndicators = [
            'javascript' => [
                'next.config.js' => 'Next.js',
                'nuxt.config.js' => 'Nuxt.js',
                'vue.config.js' => 'Vue.js',
                'angular.json' => 'Angular',
                'gatsby-config.js' => 'Gatsby',
                'svelte.config.js' => 'Svelte',
            ],
            'php' => [
                'artisan' => 'Laravel',
                'app/Console/Kernel.php' => 'Laravel',
                'bin/console' => 'Symfony',
                'wp-config.php' => 'WordPress',
                'index.php' => 'Custom PHP',
            ],
            'python' => [
                'manage.py' => 'Django',
                'app.py' => 'Flask',
                'main.py' => 'FastAPI',
                'setup.py' => 'Python Package',
            ],
        ];

        if (!isset($frameworkIndicators[$type])) {
            return null;
        }

        foreach ($frameworkIndicators[$type] as $file => $framework) {
            if (file_exists($workspacePath . '/' . $file)) {
                return $framework;
            }
        }

        return null;
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
