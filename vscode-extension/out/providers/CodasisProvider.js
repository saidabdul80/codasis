"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodasisProvider = void 0;
const vscode = require("vscode");
class CodasisProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
    }
    async askQuestion(question, context) {
        try {
            const config = vscode.workspace.getConfiguration('codasis');
            const preferredModel = config.get('preferredModel', 'deepseek-r1');
            const contextString = context ? this.formatContext(context) : '';
            const response = await this.apiClient.askQuestion({
                prompt: question,
                context: contextString,
                model: preferredModel,
                maxTokens: 2000,
                temperature: 0.7
            });
            return response.response;
        }
        catch (error) {
            console.error('Error asking question:', error);
            return 'Sorry, I encountered an error while processing your question. Please try again.';
        }
    }
    async analyzeCode(code, language) {
        try {
            const analysis = await this.apiClient.analyzeCode(code, language);
            const complexity = this.codeAnalyzer.analyzeCodeComplexity(code);
            return `# Code Analysis Report

## AI Analysis
${analysis}

## Code Metrics
- **Lines of Code**: ${complexity.linesOfCode}
- **Cyclomatic Complexity**: ${complexity.cyclomaticComplexity}
- **Functions**: ${complexity.functions}
- **Classes**: ${complexity.classes}

## Recommendations
${this.generateRecommendations(complexity)}
`;
        }
        catch (error) {
            console.error('Error analyzing code:', error);
            return 'Failed to analyze code. Please check your connection and try again.';
        }
    }
    async explainCode(code, language) {
        try {
            return await this.apiClient.explainCode(code, language);
        }
        catch (error) {
            console.error('Error explaining code:', error);
            return 'Failed to explain code. Please check your connection and try again.';
        }
    }
    async generateTests(code, language) {
        try {
            return await this.apiClient.generateTests(code, language);
        }
        catch (error) {
            console.error('Error generating tests:', error);
            return `// Failed to generate tests for ${language} code
// Please check your connection and try again.

// Original code:
${code}`;
        }
    }
    async refactorCode(code, language) {
        try {
            return await this.apiClient.refactorCode(code, language);
        }
        catch (error) {
            console.error('Error refactoring code:', error);
            return code; // Return original code if refactoring fails
        }
    }
    async getCompletions(prefix, context, language) {
        try {
            return await this.apiClient.getCompletions(prefix, context, language);
        }
        catch (error) {
            console.error('Error getting completions:', error);
            return [];
        }
    }
    formatContext(context) {
        // Format context for better AI understanding
        return `Context Information:\n${context}`;
    }
    generateRecommendations(complexity) {
        const recommendations = [];
        if (complexity.cyclomaticComplexity > 10) {
            recommendations.push('- Consider breaking down complex functions to improve maintainability');
        }
        if (complexity.linesOfCode > 100) {
            recommendations.push('- Large file detected. Consider splitting into smaller modules');
        }
        if (complexity.functions === 0 && complexity.linesOfCode > 20) {
            recommendations.push('- Consider organizing code into functions for better structure');
        }
        if (complexity.classes > 5) {
            recommendations.push('- Multiple classes in one file. Consider separating into different files');
        }
        if (recommendations.length === 0) {
            recommendations.push('- Code structure looks good! Keep up the good work.');
        }
        return recommendations.join('\n');
    }
    async getCodeSuggestions(document, position) {
        try {
            const context = await this.codeAnalyzer.getCompletionContext(document, position);
            const linePrefix = document.lineAt(position).text.substr(0, position.character);
            const suggestions = await this.getCompletions(linePrefix, context, document.languageId);
            return suggestions.map((suggestion) => {
                const item = new vscode.CompletionItem(suggestion.text, vscode.CompletionItemKind.Snippet);
                item.detail = suggestion.description;
                item.insertText = new vscode.SnippetString(suggestion.text);
                item.documentation = new vscode.MarkdownString(`**Confidence:** ${Math.round(suggestion.confidence * 100)}%`);
                item.sortText = `${1 - suggestion.confidence}${suggestion.text}`; // Higher confidence = better sort order
                return item;
            });
        }
        catch (error) {
            console.error('Error getting code suggestions:', error);
            return [];
        }
    }
    async validateConfiguration() {
        try {
            return await this.apiClient.healthCheck();
        }
        catch (error) {
            console.error('Configuration validation failed:', error);
            return false;
        }
    }
    async smartAnalyzeCode(code, language, options = {}) {
        try {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            const activeEditor = vscode.window.activeTextEditor;
            // Prepare enhanced request with context
            const request = {
                prompt: `Perform a comprehensive analysis of this ${language} code. Include:
1. Code structure and design patterns
2. Potential issues and improvements
3. Performance considerations
4. Security implications
5. Best practices recommendations

Code to analyze:
\`\`\`${language}
${code}
\`\`\``,
                focus_area: options.focus_area || 'general',
                include_tests: options.include_tests || false
            };
            const response = await this.apiClient.askQuestion(request);
            // Format the response with context information
            let analysis = response.response;
            if (response.context_used) {
                analysis += `\n\n## Context Information\n`;
                analysis += `- Context Type: ${response.context_used.type}\n`;
                analysis += `- Files Referenced: ${response.context_used.files_referenced}\n`;
                analysis += `- Dependencies Included: ${response.context_used.dependencies_included}\n`;
                analysis += `- Token Count: ${response.context_used.token_count}\n`;
            }
            return analysis;
        }
        catch (error) {
            console.error('Error in smart analysis:', error);
            return `Failed to perform smart analysis: ${error.message}`;
        }
    }
}
exports.CodasisProvider = CodasisProvider;
//# sourceMappingURL=CodasisProvider.js.map