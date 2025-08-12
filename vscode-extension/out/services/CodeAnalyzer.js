"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodeAnalyzer = void 0;
const vscode = require("vscode");
const path = require("path");
class CodeAnalyzer {
    async getCurrentContext() {
        const editor = vscode.window.activeTextEditor;
        if (!editor) {
            throw new Error('No active editor');
        }
        const document = editor.document;
        const position = editor.selection.active;
        return {
            currentFile: document.fileName,
            currentFunction: await this.getCurrentFunction(document, position),
            selectedText: this.getSelectedText(editor),
            surroundingCode: this.getSurroundingCode(document, position),
            projectStructure: await this.getProjectStructure(),
            language: document.languageId,
            imports: this.extractImports(document),
            dependencies: await this.getProjectDependencies()
        };
    }
    async getCompletionContext(document, position) {
        const config = vscode.workspace.getConfiguration('augment-ai');
        const contextLines = config.get('contextLines', 50);
        const startLine = Math.max(0, position.line - contextLines);
        const endLine = Math.min(document.lineCount - 1, position.line + contextLines);
        const range = new vscode.Range(startLine, 0, endLine, 0);
        const contextText = document.getText(range);
        // Add file information
        const fileInfo = `File: ${path.basename(document.fileName)}\nLanguage: ${document.languageId}\n\n`;
        return fileInfo + contextText;
    }
    getSelectedText(editor) {
        const selection = editor.selection;
        if (selection.isEmpty) {
            return undefined;
        }
        return editor.document.getText(selection);
    }
    getSurroundingCode(document, position, lines = 20) {
        const startLine = Math.max(0, position.line - lines);
        const endLine = Math.min(document.lineCount - 1, position.line + lines);
        const range = new vscode.Range(startLine, 0, endLine, 0);
        return document.getText(range);
    }
    async getCurrentFunction(document, position) {
        try {
            const symbols = await vscode.commands.executeCommand('vscode.executeDocumentSymbolProvider', document.uri);
            if (!symbols) {
                return undefined;
            }
            return this.findContainingFunction(symbols, position);
        }
        catch (error) {
            console.error('Error getting current function:', error);
            return undefined;
        }
    }
    findContainingFunction(symbols, position) {
        for (const symbol of symbols) {
            if (symbol.range.contains(position)) {
                if (symbol.kind === vscode.SymbolKind.Function ||
                    symbol.kind === vscode.SymbolKind.Method ||
                    symbol.kind === vscode.SymbolKind.Constructor) {
                    return symbol.name;
                }
                // Check nested symbols
                if (symbol.children) {
                    const nestedFunction = this.findContainingFunction(symbol.children, position);
                    if (nestedFunction) {
                        return nestedFunction;
                    }
                }
            }
        }
        return undefined;
    }
    extractImports(document) {
        const text = document.getText();
        const imports = [];
        // Different import patterns for different languages
        const patterns = {
            typescript: /^import\s+.*?from\s+['"]([^'"]+)['"];?$/gm,
            javascript: /^import\s+.*?from\s+['"]([^'"]+)['"];?$/gm,
            python: /^(?:from\s+(\S+)\s+import|import\s+(\S+))/gm,
            php: /^use\s+([^;]+);$/gm,
            java: /^import\s+([^;]+);$/gm,
            csharp: /^using\s+([^;]+);$/gm
        };
        const language = document.languageId;
        const pattern = patterns[language];
        if (pattern) {
            let match;
            while ((match = pattern.exec(text)) !== null) {
                imports.push(match[1] || match[2] || match[0]);
            }
        }
        return imports;
    }
    async getProjectStructure() {
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders) {
            return [];
        }
        const structure = [];
        try {
            const files = await vscode.workspace.findFiles('**/*.{js,ts,py,php,java,cs,cpp,h,hpp,c}', '**/node_modules/**', 100 // Limit to 100 files for performance
            );
            for (const file of files) {
                const relativePath = vscode.workspace.asRelativePath(file);
                structure.push(relativePath);
            }
        }
        catch (error) {
            console.error('Error getting project structure:', error);
        }
        return structure;
    }
    async getProjectDependencies() {
        const dependencies = [];
        try {
            // Check for package.json (Node.js)
            const packageJsonFiles = await vscode.workspace.findFiles('**/package.json', '**/node_modules/**', 5);
            for (const file of packageJsonFiles) {
                const content = await vscode.workspace.fs.readFile(file);
                const packageJson = JSON.parse(content.toString());
                if (packageJson.dependencies) {
                    dependencies.push(...Object.keys(packageJson.dependencies));
                }
                if (packageJson.devDependencies) {
                    dependencies.push(...Object.keys(packageJson.devDependencies));
                }
            }
            // Check for requirements.txt (Python)
            const requirementFiles = await vscode.workspace.findFiles('**/requirements.txt', undefined, 5);
            for (const file of requirementFiles) {
                const content = await vscode.workspace.fs.readFile(file);
                const lines = content.toString().split('\n');
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed && !trimmed.startsWith('#')) {
                        const packageName = trimmed.split(/[>=<]/)[0];
                        dependencies.push(packageName);
                    }
                }
            }
            // Check for composer.json (PHP)
            const composerFiles = await vscode.workspace.findFiles('**/composer.json', '**/vendor/**', 5);
            for (const file of composerFiles) {
                const content = await vscode.workspace.fs.readFile(file);
                const composerJson = JSON.parse(content.toString());
                if (composerJson.require) {
                    dependencies.push(...Object.keys(composerJson.require));
                }
                if (composerJson['require-dev']) {
                    dependencies.push(...Object.keys(composerJson['require-dev']));
                }
            }
        }
        catch (error) {
            console.error('Error getting project dependencies:', error);
        }
        return [...new Set(dependencies)]; // Remove duplicates
    }
    async analyzeCodeComplexity(code) {
        const lines = code.split('\n');
        const linesOfCode = lines.filter(line => line.trim() && !line.trim().startsWith('//')).length;
        // Simple complexity analysis
        const complexityKeywords = ['if', 'else', 'while', 'for', 'switch', 'case', 'catch', 'try'];
        let cyclomaticComplexity = 1; // Base complexity
        for (const line of lines) {
            for (const keyword of complexityKeywords) {
                if (line.includes(keyword)) {
                    cyclomaticComplexity++;
                }
            }
        }
        const functions = (code.match(/function\s+\w+|def\s+\w+|public\s+\w+\s*\(/g) || []).length;
        const classes = (code.match(/class\s+\w+/g) || []).length;
        return {
            cyclomaticComplexity,
            linesOfCode,
            functions,
            classes
        };
    }
}
exports.CodeAnalyzer = CodeAnalyzer;
//# sourceMappingURL=CodeAnalyzer.js.map