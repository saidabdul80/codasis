<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CodeAnalysisService
{
    private array $languagePatterns = [
        'php' => [
            'class' => '/class\s+(\w+)/i',
            'function' => '/function\s+(\w+)/i',
            'interface' => '/interface\s+(\w+)/i',
            'trait' => '/trait\s+(\w+)/i',
            'namespace' => '/namespace\s+([\w\\\\]+)/i',
            'use' => '/use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?/i',
        ],
        'javascript' => [
            'class' => '/class\s+(\w+)/i',
            'function' => '/(?:function\s+(\w+)|(\w+)\s*[:=]\s*(?:function|\([^)]*\)\s*=>))/i',
            'const' => '/const\s+(\w+)/i',
            'let' => '/let\s+(\w+)/i',
            'var' => '/var\s+(\w+)/i',
            'import' => '/import\s+.*?from\s+[\'"]([^\'"]+)[\'"]/i',
            'export' => '/export\s+(?:default\s+)?(?:class|function|const|let|var)\s+(\w+)/i',
        ],
        'typescript' => [
            'class' => '/class\s+(\w+)/i',
            'interface' => '/interface\s+(\w+)/i',
            'type' => '/type\s+(\w+)/i',
            'function' => '/(?:function\s+(\w+)|(\w+)\s*[:=]\s*(?:function|\([^)]*\)\s*=>))/i',
            'const' => '/const\s+(\w+)/i',
            'import' => '/import\s+.*?from\s+[\'"]([^\'"]+)[\'"]/i',
            'export' => '/export\s+(?:default\s+)?(?:class|interface|type|function|const)\s+(\w+)/i',
        ],
        'python' => [
            'class' => '/class\s+(\w+)/i',
            'function' => '/def\s+(\w+)/i',
            'import' => '/(?:from\s+[\w.]+\s+)?import\s+([\w.,\s*]+)/i',
        ],
        'java' => [
            'class' => '/(?:public\s+)?class\s+(\w+)/i',
            'interface' => '/(?:public\s+)?interface\s+(\w+)/i',
            'method' => '/(?:public|private|protected)?\s*(?:static\s+)?[\w<>\[\]]+\s+(\w+)\s*\(/i',
            'import' => '/import\s+([\w.]+)/i',
            'package' => '/package\s+([\w.]+)/i',
        ],
    ];

    public function analyzeFile(string $filePath, string $content): array
    {
        $language = $this->detectLanguage($filePath);
        $lines = explode("\n", $content);
        
        $analysis = [
            'file_path' => $filePath,
            'language' => $language,
            'line_count' => count($lines),
            'size_bytes' => strlen($content),
            'symbols' => $this->extractSymbols($content, $language),
            'imports' => $this->extractImports($content, $language),
            'exports' => $this->extractExports($content, $language),
            'complexity' => $this->calculateComplexity($content, $language),
            'dependencies' => $this->extractDependencies($content, $language),
            'functions' => $this->extractFunctions($content, $language),
            'classes' => $this->extractClasses($content, $language),
            'comments' => $this->extractComments($content, $language),
            'todos' => $this->extractTodos($content),
        ];

        return $analysis;
    }

    public function detectLanguage(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $languageMap = [
            'php' => 'php',
            'js' => 'javascript',
            'jsx' => 'javascript',
            'ts' => 'typescript',
            'tsx' => 'typescript',
            'py' => 'python',
            'java' => 'java',
            'kt' => 'kotlin',
            'swift' => 'swift',
            'go' => 'go',
            'rs' => 'rust',
            'cpp' => 'cpp',
            'c' => 'c',
            'cs' => 'csharp',
            'rb' => 'ruby',
            'vue' => 'vue',
            'html' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'json' => 'json',
            'xml' => 'xml',
            'yaml' => 'yaml',
            'yml' => 'yaml',
            'md' => 'markdown',
            'sql' => 'sql',
        ];

        return $languageMap[$extension] ?? 'text';
    }

    public function extractSymbols(string $content, string $language): array
    {
        if (!isset($this->languagePatterns[$language])) {
            return [];
        }

        $symbols = [];
        $patterns = $this->languagePatterns[$language];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    if (!empty($match[0])) {
                        $symbols[] = [
                            'name' => $match[0],
                            'type' => $type,
                            'position' => $match[1],
                            'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                        ];
                    }
                }
            }
        }

        return $symbols;
    }

    public function extractImports(string $content, string $language): array
    {
        $imports = [];
        
        if (!isset($this->languagePatterns[$language]['import'])) {
            return $imports;
        }

        $pattern = $this->languagePatterns[$language]['import'];
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                if (!empty($match[0])) {
                    $imports[] = [
                        'module' => $match[0],
                        'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                    ];
                }
            }
        }

        return $imports;
    }

    public function extractExports(string $content, string $language): array
    {
        $exports = [];
        
        if (!isset($this->languagePatterns[$language]['export'])) {
            return $exports;
        }

        $pattern = $this->languagePatterns[$language]['export'];
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                if (!empty($match[0])) {
                    $exports[] = [
                        'name' => $match[0],
                        'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                    ];
                }
            }
        }

        return $exports;
    }

    public function calculateComplexity(string $content, string $language): array
    {
        $lines = explode("\n", $content);
        $nonEmptyLines = array_filter($lines, fn($line) => !empty(trim($line)));
        $commentLines = $this->countCommentLines($content, $language);
        
        // Cyclomatic complexity indicators
        $complexityKeywords = ['if', 'else', 'elseif', 'while', 'for', 'foreach', 'switch', 'case', 'catch', 'try'];
        $complexity = 1; // Base complexity
        
        foreach ($complexityKeywords as $keyword) {
            $complexity += substr_count(strtolower($content), $keyword);
        }

        return [
            'total_lines' => count($lines),
            'code_lines' => count($nonEmptyLines) - $commentLines,
            'comment_lines' => $commentLines,
            'cyclomatic_complexity' => $complexity,
            'maintainability_index' => $this->calculateMaintainabilityIndex($complexity, count($nonEmptyLines)),
        ];
    }

    public function extractDependencies(string $content, string $language): array
    {
        $dependencies = [];
        $imports = $this->extractImports($content, $language);
        
        foreach ($imports as $import) {
            $module = $import['module'];
            
            // Determine if it's a local or external dependency
            $isLocal = $this->isLocalDependency($module, $language);
            
            $dependencies[] = [
                'module' => $module,
                'type' => $isLocal ? 'local' : 'external',
                'line' => $import['line'],
            ];
        }

        return $dependencies;
    }

    public function extractFunctions(string $content, string $language): array
    {
        $functions = [];
        $symbols = $this->extractSymbols($content, $language);
        
        foreach ($symbols as $symbol) {
            if ($symbol['type'] === 'function') {
                $functions[] = [
                    'name' => $symbol['name'],
                    'line' => $symbol['line'],
                    'complexity' => $this->calculateFunctionComplexity($content, $symbol['name'], $language),
                ];
            }
        }

        return $functions;
    }

    public function extractClasses(string $content, string $language): array
    {
        $classes = [];
        $symbols = $this->extractSymbols($content, $language);
        
        foreach ($symbols as $symbol) {
            if ($symbol['type'] === 'class') {
                $classes[] = [
                    'name' => $symbol['name'],
                    'line' => $symbol['line'],
                    'methods' => $this->extractClassMethods($content, $symbol['name'], $language),
                ];
            }
        }

        return $classes;
    }

    public function extractComments(string $content, string $language): array
    {
        $comments = [];
        $lines = explode("\n", $content);
        
        $commentPatterns = [
            'php' => ['/\/\*.*?\*\//s', '/\/\/.*$/m', '/#.*$/m'],
            'javascript' => ['/\/\*.*?\*\//s', '/\/\/.*$/m'],
            'typescript' => ['/\/\*.*?\*\//s', '/\/\/.*$/m'],
            'python' => ['/""".*?"""/s', "/'.*?'/s", '/#.*$/m'],
            'java' => ['/\/\*.*?\*\//s', '/\/\/.*$/m'],
        ];

        if (isset($commentPatterns[$language])) {
            foreach ($commentPatterns[$language] as $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $comments[] = [
                            'content' => trim($match[0]),
                            'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                        ];
                    }
                }
            }
        }

        return $comments;
    }

    public function extractTodos(string $content): array
    {
        $todos = [];
        $pattern = '/(?:TODO|FIXME|HACK|NOTE|BUG):\s*(.+)/i';
        
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $todos[] = [
                    'content' => trim($match[0]),
                    'description' => trim($matches[1][$index][0]),
                    'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                ];
            }
        }

        return $todos;
    }

    private function countCommentLines(string $content, string $language): int
    {
        $comments = $this->extractComments($content, $language);
        $commentLines = 0;
        
        foreach ($comments as $comment) {
            $commentLines += substr_count($comment['content'], "\n") + 1;
        }

        return $commentLines;
    }

    private function calculateMaintainabilityIndex(int $complexity, int $linesOfCode): float
    {
        // Simplified maintainability index calculation
        if ($linesOfCode === 0) return 100.0;
        
        $mi = 171 - 5.2 * log($linesOfCode) - 0.23 * $complexity - 16.2 * log($linesOfCode);
        return max(0, min(100, $mi));
    }

    private function isLocalDependency(string $module, string $language): bool
    {
        // Simple heuristic to determine if a dependency is local
        return str_starts_with($module, '.') || 
               str_starts_with($module, '/') || 
               str_starts_with($module, '\\') ||
               (!str_contains($module, '/') && $language === 'php');
    }

    private function calculateFunctionComplexity(string $content, string $functionName, string $language): int
    {
        // Extract function body and calculate complexity
        $pattern = "/function\s+{$functionName}\s*\([^)]*\)\s*\{/i";
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $start = $matches[0][1];
            $braceCount = 0;
            $end = $start;
            
            for ($i = $start; $i < strlen($content); $i++) {
                if ($content[$i] === '{') $braceCount++;
                if ($content[$i] === '}') $braceCount--;
                if ($braceCount === 0) {
                    $end = $i;
                    break;
                }
            }
            
            $functionBody = substr($content, $start, $end - $start);
            return $this->calculateComplexity($functionBody, $language)['cyclomatic_complexity'];
        }
        
        return 1;
    }

    private function extractClassMethods(string $content, string $className, string $language): array
    {
        // This is a simplified implementation
        // In a real implementation, you'd use proper AST parsing
        $methods = [];
        $functions = $this->extractFunctions($content, $language);
        
        // For now, return all functions as potential methods
        return $functions;
    }
}
