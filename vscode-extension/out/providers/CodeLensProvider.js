"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodeLensProvider = void 0;
const vscode = require("vscode");
class CodeLensProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
        this._onDidChangeCodeLenses = new vscode.EventEmitter();
        this.onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;
        // Refresh code lenses when configuration changes
        vscode.workspace.onDidChangeConfiguration(e => {
            if (e.affectsConfiguration('codasis.enableCodeLens')) {
                this._onDidChangeCodeLenses.fire();
            }
        });
    }
    async provideCodeLenses(document, token) {
        // Check if code lens is enabled
        const config = vscode.workspace.getConfiguration('codasis');
        if (!config.get('enableCodeLens', true)) {
            return [];
        }
        const codeLenses = [];
        try {
            // Analyze the document to find functions, classes, and other interesting code
            const analysis = this.analyzeDocument(document);
            // Add code lenses for functions
            for (const func of analysis.functions) {
                codeLenses.push(...this.createFunctionCodeLenses(func, document));
            }
            // Add code lenses for classes
            for (const cls of analysis.classes) {
                codeLenses.push(...this.createClassCodeLenses(cls, document));
            }
            // Add code lenses for complex blocks
            for (const block of analysis.complexBlocks) {
                codeLenses.push(...this.createComplexityCodeLenses(block, document));
            }
            // Add file-level code lenses
            codeLenses.push(...this.createFileCodeLenses(document));
        }
        catch (error) {
            console.error('Error providing code lenses:', error);
        }
        return codeLenses;
    }
    analyzeDocument(document) {
        const text = document.getText();
        const lines = text.split('\n');
        const analysis = {
            functions: [],
            classes: [],
            complexBlocks: []
        };
        // Extract functions
        const functions = this.codeAnalyzer.extractFunctions(text, document.languageId);
        analysis.functions = functions.map(func => ({
            ...func,
            range: new vscode.Range(func.line - 1, 0, func.line - 1, 0)
        }));
        // Extract classes
        const classes = this.codeAnalyzer.extractClasses(text, document.languageId);
        analysis.classes = classes.map(cls => ({
            ...cls,
            range: new vscode.Range(cls.line - 1, 0, cls.line - 1, 0)
        }));
        // Find complex blocks (high cyclomatic complexity)
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const complexity = this.estimateLineComplexity(line);
            if (complexity > 3) {
                analysis.complexBlocks.push({
                    line: i + 1,
                    complexity: complexity,
                    range: new vscode.Range(i, 0, i, 0),
                    content: line.trim()
                });
            }
        }
        return analysis;
    }
    createFunctionCodeLenses(func, document) {
        const codeLenses = [];
        const range = func.range;
        // "Explain Function" code lens
        codeLenses.push(new vscode.CodeLens(range, {
            title: "🧠 Explain",
            tooltip: "Get AI explanation of this function",
            command: 'codasis.explainFunction',
            arguments: [document.uri, func.name, func.line]
        }));
        // "Generate Tests" code lens
        codeLenses.push(new vscode.CodeLens(range, {
            title: "🧪 Generate Tests",
            tooltip: "Generate unit tests for this function",
            command: 'codasis.generateTestsForFunction',
            arguments: [document.uri, func.name, func.line]
        }));
        // "Optimize" code lens for complex functions
        if (func.complexity && func.complexity > 5) {
            codeLenses.push(new vscode.CodeLens(range, {
                title: "⚡ Optimize",
                tooltip: "Get optimization suggestions",
                command: 'codasis.optimizeFunction',
                arguments: [document.uri, func.name, func.line]
            }));
        }
        return codeLenses;
    }
    createClassCodeLenses(cls, document) {
        const codeLenses = [];
        const range = cls.range;
        // "Explain Class" code lens
        codeLenses.push(new vscode.CodeLens(range, {
            title: "🧠 Explain Class",
            tooltip: "Get AI explanation of this class",
            command: 'codasis.explainClass',
            arguments: [document.uri, cls.name, cls.line]
        }));
        // "Generate Documentation" code lens
        codeLenses.push(new vscode.CodeLens(range, {
            title: "📝 Document",
            tooltip: "Generate documentation for this class",
            command: 'codasis.generateDocumentation',
            arguments: [document.uri, cls.name, cls.line]
        }));
        return codeLenses;
    }
    createComplexityCodeLenses(block, document) {
        const codeLenses = [];
        const range = block.range;
        // "Simplify" code lens for complex code
        codeLenses.push(new vscode.CodeLens(range, {
            title: `🔧 Simplify (complexity: ${block.complexity})`,
            tooltip: "Get suggestions to reduce complexity",
            command: 'codasis.simplifyCode',
            arguments: [document.uri, block.line, block.content]
        }));
        return codeLenses;
    }
    createFileCodeLenses(document) {
        const codeLenses = [];
        // Add file-level code lens at the top
        const topRange = new vscode.Range(0, 0, 0, 0);
        // "Analyze File" code lens
        codeLenses.push(new vscode.CodeLens(topRange, {
            title: "🔍 Analyze File",
            tooltip: "Get comprehensive file analysis",
            command: 'codasis.analyzeFile',
            arguments: [document.uri]
        }));
        // "Generate Summary" code lens
        codeLenses.push(new vscode.CodeLens(topRange, {
            title: "📋 Summarize",
            tooltip: "Generate file summary",
            command: 'codasis.summarizeFile',
            arguments: [document.uri]
        }));
        return codeLenses;
    }
    estimateLineComplexity(line) {
        let complexity = 0;
        // Count complexity indicators
        const complexityPatterns = [
            /\bif\b/g,
            /\belse\b/g,
            /\bwhile\b/g,
            /\bfor\b/g,
            /\bswitch\b/g,
            /\bcase\b/g,
            /\btry\b/g,
            /\bcatch\b/g,
            /&&/g,
            /\|\|/g,
            /\?.*:/g // ternary operator
        ];
        for (const pattern of complexityPatterns) {
            const matches = line.match(pattern);
            if (matches) {
                complexity += matches.length;
            }
        }
        return complexity;
    }
    resolveCodeLens(codeLens, token) {
        // Code lenses are already resolved in provideCodeLenses
        return codeLens;
    }
}
exports.CodeLensProvider = CodeLensProvider;
//# sourceMappingURL=CodeLensProvider.js.map