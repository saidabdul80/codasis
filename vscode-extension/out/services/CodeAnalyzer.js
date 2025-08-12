"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodeAnalyzer = void 0;
const vscode = require("vscode");
class CodeAnalyzer {
    async getCurrentContext() {
        const editor = vscode.window.activeTextEditor;
        if (!editor) {
            return '';
        }
        const document = editor.document;
        const position = editor.selection.active;
        // Get surrounding context (50 lines before and after)
        const startLine = Math.max(0, position.line - 25);
        const endLine = Math.min(document.lineCount - 1, position.line + 25);
        const contextRange = new vscode.Range(startLine, 0, endLine, 0);
        const contextText = document.getText(contextRange);
        return `File: ${document.fileName}\nLanguage: ${document.languageId}\n\nContext:\n${contextText}`;
    }
    async getCompletionContext(document, position) {
        // Get context around the current position for completions
        const startLine = Math.max(0, position.line - 10);
        const endLine = Math.min(document.lineCount - 1, position.line + 5);
        const contextRange = new vscode.Range(startLine, 0, endLine, 0);
        return document.getText(contextRange);
    }
    analyzeCodeComplexity(code) {
        const lines = code.split('\n');
        const nonEmptyLines = lines.filter(line => line.trim().length > 0);
        // Simple complexity analysis
        const functions = (code.match(/function\s+\w+|def\s+\w+|class\s+\w+/g) || []).length;
        const classes = (code.match(/class\s+\w+/g) || []).length;
        const conditionals = (code.match(/if\s*\(|else|elif|switch|case/g) || []).length;
        const loops = (code.match(/for\s*\(|while\s*\(|foreach/g) || []).length;
        const cyclomaticComplexity = 1 + conditionals + loops;
        return {
            linesOfCode: lines.length,
            nonEmptyLines: nonEmptyLines.length,
            functions,
            classes,
            cyclomaticComplexity,
            complexity: cyclomaticComplexity > 10 ? 'High' : cyclomaticComplexity > 5 ? 'Medium' : 'Low'
        };
    }
    extractImports(code, language) {
        const imports = [];
        const patterns = {
            'javascript': [
                /import\s+.*?\s+from\s+['"]([^'"]+)['"]/g,
                /require\s*\(\s*['"]([^'"]+)['"]\s*\)/g
            ],
            'typescript': [
                /import\s+.*?\s+from\s+['"]([^'"]+)['"]/g,
                /require\s*\(\s*['"]([^'"]+)['"]\s*\)/g
            ],
            'python': [
                /from\s+(\S+)\s+import/g,
                /import\s+(\S+)/g
            ],
            'php': [
                /use\s+([\w\\]+)/g,
                /require_once\s+['"]([^'"]+)['"]/g,
                /include_once\s+['"]([^'"]+)['"]/g
            ]
        };
        const languagePatterns = patterns[language] || [];
        for (const pattern of languagePatterns) {
            let match;
            while ((match = pattern.exec(code)) !== null) {
                imports.push(match[1]);
            }
        }
        return imports;
    }
    extractFunctions(code, language) {
        const functions = [];
        const lines = code.split('\n');
        const patterns = {
            'javascript': /function\s+(\w+)|(\w+)\s*[:=]\s*(?:function|\([^)]*\)\s*=>)/,
            'typescript': /function\s+(\w+)|(\w+)\s*[:=]\s*(?:function|\([^)]*\)\s*=>)/,
            'python': /def\s+(\w+)/,
            'php': /function\s+(\w+)/,
            'java': /(?:public|private|protected)?\s*(?:static\s+)?[\w<>\[\]]+\s+(\w+)\s*\(/,
            'csharp': /(?:public|private|protected)?\s*(?:static\s+)?[\w<>\[\]]+\s+(\w+)\s*\(/
        };
        const pattern = patterns[language];
        if (!pattern)
            return functions;
        lines.forEach((line, index) => {
            const match = line.match(pattern);
            if (match) {
                const functionName = match[1] || match[2];
                if (functionName) {
                    functions.push({
                        name: functionName,
                        line: index + 1
                    });
                }
            }
        });
        return functions;
    }
    extractClasses(code, language) {
        const classes = [];
        const lines = code.split('\n');
        const patterns = {
            'javascript': /class\s+(\w+)/,
            'typescript': /class\s+(\w+)/,
            'python': /class\s+(\w+)/,
            'php': /class\s+(\w+)/,
            'java': /(?:public\s+)?class\s+(\w+)/,
            'csharp': /(?:public\s+)?class\s+(\w+)/
        };
        const pattern = patterns[language];
        if (!pattern)
            return classes;
        lines.forEach((line, index) => {
            const match = line.match(pattern);
            if (match) {
                classes.push({
                    name: match[1],
                    line: index + 1
                });
            }
        });
        return classes;
    }
    getFileMetadata(document) {
        return {
            fileName: document.fileName,
            language: document.languageId,
            lineCount: document.lineCount,
            size: document.getText().length,
            lastModified: new Date().toISOString()
        };
    }
}
exports.CodeAnalyzer = CodeAnalyzer;
//# sourceMappingURL=CodeAnalyzer.js.map