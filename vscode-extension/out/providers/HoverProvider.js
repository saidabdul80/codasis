"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.HoverProvider = void 0;
const vscode = require("vscode");
class HoverProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
        this.cache = new Map();
        this.cacheTimeout = 60000; // 1 minute
    }
    async provideHover(document, position, token) {
        // Check if hover info is enabled
        const config = vscode.workspace.getConfiguration('codasis');
        if (!config.get('enableHoverInfo', true)) {
            return undefined;
        }
        try {
            // Get the word at the current position
            const wordRange = document.getWordRangeAtPosition(position);
            if (!wordRange) {
                return undefined;
            }
            const word = document.getText(wordRange);
            if (!word || word.length < 2) {
                return undefined;
            }
            // Create cache key
            const cacheKey = `${document.uri.toString()}:${position.line}:${position.character}:${word}`;
            // Check cache first
            const cached = this.cache.get(cacheKey);
            if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.hover;
            }
            // Get context around the word
            const line = document.lineAt(position);
            const contextRange = new vscode.Range(Math.max(0, position.line - 5), 0, Math.min(document.lineCount - 1, position.line + 5), 0);
            const context = document.getText(contextRange);
            // Analyze what kind of symbol this is
            const symbolInfo = this.analyzeSymbol(word, line.text, context, document.languageId);
            if (!symbolInfo.shouldProvideHover) {
                return undefined;
            }
            // Prepare request for AI explanation
            const hoverRequest = {
                prompt: this.buildHoverPrompt(word, symbolInfo, context, document.languageId),
                context: context,
                language: document.languageId,
                maxTokens: 300,
                temperature: 0.2,
                current_file: document.uri.fsPath,
                focus_area: 'explanation'
            };
            // Get AI explanation
            const response = await this.apiClient.askQuestion(hoverRequest);
            if (token.isCancellationRequested) {
                return undefined;
            }
            // Create hover content
            const hoverContent = this.createHoverContent(word, symbolInfo, response.response);
            const hover = new vscode.Hover(hoverContent, wordRange);
            // Cache the result
            this.cache.set(cacheKey, {
                hover: hover,
                timestamp: Date.now()
            });
            // Clean old cache entries
            this.cleanCache();
            return hover;
        }
        catch (error) {
            console.error('Error providing hover info:', error);
            return undefined;
        }
    }
    analyzeSymbol(word, line, context, language) {
        const symbolInfo = {
            word: word,
            type: 'unknown',
            shouldProvideHover: false,
            confidence: 0
        };
        // Language-specific patterns
        const patterns = {
            'javascript': {
                'function': new RegExp(`function\\s+${word}|${word}\\s*[:=]\\s*(?:function|\\([^)]*\\)\\s*=>)`),
                'variable': new RegExp(`(?:const|let|var)\\s+${word}`),
                'class': new RegExp(`class\\s+${word}`),
                'method': new RegExp(`${word}\\s*\\(`),
                'property': new RegExp(`\\.${word}\\b`)
            },
            'typescript': {
                'function': new RegExp(`function\\s+${word}|${word}\\s*[:=]\\s*(?:function|\\([^)]*\\)\\s*=>)`),
                'variable': new RegExp(`(?:const|let|var)\\s+${word}`),
                'class': new RegExp(`class\\s+${word}`),
                'interface': new RegExp(`interface\\s+${word}`),
                'type': new RegExp(`type\\s+${word}`),
                'method': new RegExp(`${word}\\s*\\(`),
                'property': new RegExp(`\\.${word}\\b`)
            },
            'python': {
                'function': new RegExp(`def\\s+${word}`),
                'class': new RegExp(`class\\s+${word}`),
                'variable': new RegExp(`${word}\\s*=`),
                'method': new RegExp(`def\\s+${word}\\s*\\(`)
            },
            'php': {
                'function': new RegExp(`function\\s+${word}`),
                'class': new RegExp(`class\\s+${word}`),
                'variable': new RegExp(`\\$${word}`),
                'method': new RegExp(`function\\s+${word}\\s*\\(`)
            }
        };
        const languagePatterns = patterns[language];
        if (!languagePatterns) {
            return symbolInfo;
        }
        // Check what type of symbol this is
        for (const [type, pattern] of Object.entries(languagePatterns)) {
            if (pattern.test(context)) {
                symbolInfo.type = type;
                symbolInfo.shouldProvideHover = true;
                symbolInfo.confidence = 0.8;
                break;
            }
        }
        // If not found in context, check if it looks like a method call
        if (!symbolInfo.shouldProvideHover && line.includes(`${word}(`)) {
            symbolInfo.type = 'method_call';
            symbolInfo.shouldProvideHover = true;
            symbolInfo.confidence = 0.6;
        }
        // Check if it's a property access
        if (!symbolInfo.shouldProvideHover && line.includes(`.${word}`)) {
            symbolInfo.type = 'property_access';
            symbolInfo.shouldProvideHover = true;
            symbolInfo.confidence = 0.5;
        }
        // Skip common keywords and short words
        const commonKeywords = ['if', 'for', 'while', 'do', 'try', 'catch', 'new', 'this', 'return', 'true', 'false', 'null', 'undefined'];
        if (commonKeywords.includes(word.toLowerCase()) || word.length < 3) {
            symbolInfo.shouldProvideHover = false;
        }
        return symbolInfo;
    }
    buildHoverPrompt(word, symbolInfo, context, language) {
        const typeDescription = symbolInfo.type === 'unknown' ? 'symbol' : symbolInfo.type;
        return `Explain the ${language} ${typeDescription} "${word}" in this code context. Be concise and focus on:
1. What it does/represents
2. Its purpose in this context
3. Key parameters or properties (if applicable)

Provide a brief, helpful explanation in 2-3 sentences.

Code context:
${context}

Focus on the symbol: ${word}`;
    }
    createHoverContent(word, symbolInfo, aiResponse) {
        const content = [];
        // Header with symbol info
        const header = new vscode.MarkdownString();
        header.isTrusted = true;
        const typeIcon = this.getTypeIcon(symbolInfo.type);
        header.appendMarkdown(`### ${typeIcon} \`${word}\`\n`);
        if (symbolInfo.type !== 'unknown') {
            header.appendMarkdown(`*${symbolInfo.type}*\n\n`);
        }
        content.push(header);
        // AI explanation
        const explanation = new vscode.MarkdownString();
        explanation.isTrusted = true;
        // Clean up the AI response
        const cleanResponse = aiResponse
            .replace(/```[\w]*\n?/g, '') // Remove code block markers
            .replace(/\n{3,}/g, '\n\n') // Remove excessive newlines
            .trim();
        explanation.appendMarkdown(cleanResponse);
        content.push(explanation);
        // Footer with Codasis branding
        const footer = new vscode.MarkdownString();
        footer.isTrusted = true;
        footer.appendMarkdown('\n---\n*🧠 Powered by Codasis AI*');
        content.push(footer);
        return content;
    }
    getTypeIcon(type) {
        const icons = {
            'function': '⚡',
            'method': '⚡',
            'method_call': '📞',
            'class': '🏗️',
            'interface': '📋',
            'type': '🏷️',
            'variable': '📦',
            'property': '🔧',
            'property_access': '🔍',
            'unknown': '❓'
        };
        return icons[type] || '❓';
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
exports.HoverProvider = HoverProvider;
//# sourceMappingURL=HoverProvider.js.map