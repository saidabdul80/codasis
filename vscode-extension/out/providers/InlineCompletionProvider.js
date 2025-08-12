"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.InlineCompletionProvider = void 0;
const vscode = require("vscode");
class InlineCompletionProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
        this.cache = new Map();
        this.cacheTimeout = 30000; // 30 seconds
    }
    async provideInlineCompletionItems(document, position, context, token) {
        // Check if inline suggestions are enabled
        const config = vscode.workspace.getConfiguration('codasis');
        if (!config.get('inlineSuggestions', true)) {
            return undefined;
        }
        // Don't provide suggestions if user is actively typing
        if (context.triggerKind === vscode.InlineCompletionTriggerKind.Automatic) {
            // Add a small delay to avoid too many requests
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        if (token.isCancellationRequested) {
            return undefined;
        }
        try {
            const line = document.lineAt(position);
            const linePrefix = line.text.substring(0, position.character);
            const lineSuffix = line.text.substring(position.character);
            // Skip if line is empty or just whitespace
            if (linePrefix.trim().length === 0) {
                return undefined;
            }
            // Create cache key
            const cacheKey = `${document.uri.toString()}:${position.line}:${linePrefix}`;
            // Check cache first
            const cached = this.cache.get(cacheKey);
            if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.items;
            }
            // Get context around current position
            const contextLines = config.get('contextLines', 50);
            const startLine = Math.max(0, position.line - Math.floor(contextLines / 2));
            const endLine = Math.min(document.lineCount - 1, position.line + Math.floor(contextLines / 2));
            const contextRange = new vscode.Range(startLine, 0, endLine, 0);
            const contextText = document.getText(contextRange);
            // Prepare request for AI completion
            const completionRequest = {
                prompt: this.buildCompletionPrompt(linePrefix, lineSuffix, contextText, document.languageId),
                context: contextText,
                language: document.languageId,
                maxTokens: 150,
                temperature: 0.3,
                current_file: document.uri.fsPath
            };
            // Get AI suggestions
            const response = await this.apiClient.askQuestion(completionRequest);
            if (token.isCancellationRequested) {
                return undefined;
            }
            // Parse AI response into completion items
            const completionItems = this.parseCompletionResponse(response.response, linePrefix, lineSuffix);
            // Cache the results
            this.cache.set(cacheKey, {
                items: completionItems,
                timestamp: Date.now()
            });
            // Clean old cache entries
            this.cleanCache();
            return completionItems;
        }
        catch (error) {
            console.error('Error providing inline completions:', error);
            return undefined;
        }
    }
    buildCompletionPrompt(linePrefix, lineSuffix, context, language) {
        return `Complete the following ${language} code. Provide only the completion text, no explanations.

Context:
${context}

Current line prefix: ${linePrefix}
Current line suffix: ${lineSuffix}

Complete the code after the prefix:`;
    }
    parseCompletionResponse(response, linePrefix, lineSuffix) {
        const items = [];
        try {
            // Extract code blocks from response
            const codeBlockRegex = /```[\w]*\n([\s\S]*?)\n```/g;
            let match;
            while ((match = codeBlockRegex.exec(response)) !== null) {
                const code = match[1].trim();
                if (code && !code.includes(linePrefix)) {
                    items.push(new vscode.InlineCompletionItem(code));
                }
            }
            // If no code blocks found, try to extract direct completion
            if (items.length === 0) {
                const lines = response.split('\n');
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed &&
                        !trimmed.startsWith('//') &&
                        !trimmed.startsWith('/*') &&
                        !trimmed.startsWith('*') &&
                        !trimmed.includes('Complete') &&
                        !trimmed.includes('completion')) {
                        // Check if this looks like a valid completion
                        if (this.isValidCompletion(trimmed, linePrefix)) {
                            items.push(new vscode.InlineCompletionItem(trimmed));
                            break; // Only take the first valid completion
                        }
                    }
                }
            }
            // Limit to 3 suggestions max
            return items.slice(0, 3);
        }
        catch (error) {
            console.error('Error parsing completion response:', error);
            return [];
        }
    }
    isValidCompletion(completion, linePrefix) {
        // Basic validation to ensure the completion makes sense
        // Should not be too short or too long
        if (completion.length < 2 || completion.length > 200) {
            return false;
        }
        // Should not repeat the prefix
        if (completion.startsWith(linePrefix.trim())) {
            return false;
        }
        // Should not be just a comment
        if (completion.startsWith('//') || completion.startsWith('/*') || completion.startsWith('#')) {
            return false;
        }
        // Should contain some code-like characters
        const codePatterns = /[(){}\[\];=+\-*/<>]/;
        if (!codePatterns.test(completion)) {
            return false;
        }
        return true;
    }
    cleanCache() {
        const now = Date.now();
        for (const [key, value] of this.cache.entries()) {
            if (now - value.timestamp > this.cacheTimeout) {
                this.cache.delete(key);
            }
        }
    }
}
exports.InlineCompletionProvider = InlineCompletionProvider;
//# sourceMappingURL=InlineCompletionProvider.js.map