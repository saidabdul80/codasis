"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.APIClient = void 0;
const axios_1 = require("axios");
const vscode = require("vscode");
class APIClient {
    constructor() {
        this.client = axios_1.default.create();
        this.setupInterceptors();
    }
    setupInterceptors() {
        // Request interceptor to add auth and base URL
        this.client.interceptors.request.use((config) => {
            const vsConfig = vscode.workspace.getConfiguration('augment-ai');
            const apiUrl = vsConfig.get('apiUrl', 'http://localhost:8000/api');
            const apiKey = vsConfig.get('apiKey', '');
            config.baseURL = apiUrl;
            if (apiKey) {
                config.headers.Authorization = `Bearer ${apiKey}`;
            }
            config.headers['Content-Type'] = 'application/json';
            config.timeout = 30000; // 30 seconds
            return config;
        });
        // Response interceptor for error handling
        this.client.interceptors.response.use((response) => response, (error) => {
            if (error.response?.status === 401) {
                vscode.window.showErrorMessage('Authentication failed. Please check your API key in settings.');
            }
            else if (error.response?.status === 429) {
                vscode.window.showErrorMessage('Rate limit exceeded. Please try again later.');
            }
            else if (error.code === 'ECONNREFUSED') {
                vscode.window.showErrorMessage('Cannot connect to backend. Make sure the server is running.');
            }
            else {
                vscode.window.showErrorMessage(`API Error: ${error.message}`);
            }
            throw error;
        });
    }
    async askQuestion(request) {
        try {
            // Add workspace context if available
            const workspaceContext = await this.getWorkspaceContext();
            const enhancedRequest = {
                ...request,
                ...workspaceContext
            };
            const response = await this.client.post('/ai/ask', enhancedRequest);
            return response.data;
        }
        catch (error) {
            console.error('Error asking question:', error);
            throw new Error('Failed to get AI response');
        }
    }
    async getWorkspaceContext() {
        try {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            const activeEditor = vscode.window.activeTextEditor;
            const context = {};
            if (workspaceFolders && workspaceFolders.length > 0) {
                context.workspace_path = workspaceFolders[0].uri.fsPath;
            }
            if (activeEditor) {
                context.current_file = activeEditor.document.uri.fsPath;
                context.language = activeEditor.document.languageId;
            }
            return context;
        }
        catch (error) {
            console.error('Error getting workspace context:', error);
            return {};
        }
    }
    async indexWorkspace(workspacePath, forceReindex = false) {
        try {
            const response = await this.client.post('/ai/index-workspace', {
                workspace_path: workspacePath,
                force_reindex: forceReindex
            });
            return response.data;
        }
        catch (error) {
            console.error('Error indexing workspace:', error);
            throw new Error('Failed to index workspace');
        }
    }
    async analyzeCode(code, language) {
        try {
            const response = await this.client.post('/ai/analyze', {
                code,
                language
            });
            return response.data.analysis;
        }
        catch (error) {
            console.error('Error analyzing code:', error);
            throw new Error('Failed to analyze code');
        }
    }
    async explainCode(code, language) {
        try {
            const response = await this.client.post('/ai/explain', {
                code,
                language
            });
            return response.data.explanation;
        }
        catch (error) {
            console.error('Error explaining code:', error);
            throw new Error('Failed to explain code');
        }
    }
    async generateTests(code, language) {
        try {
            const response = await this.client.post('/ai/generate-tests', {
                code,
                language
            });
            return response.data.tests;
        }
        catch (error) {
            console.error('Error generating tests:', error);
            throw new Error('Failed to generate tests');
        }
    }
    async refactorCode(code, language) {
        try {
            const response = await this.client.post('/ai/refactor', {
                code,
                language
            });
            return response.data.refactored_code;
        }
        catch (error) {
            console.error('Error refactoring code:', error);
            throw new Error('Failed to refactor code');
        }
    }
    async getCompletions(prefix, context, language) {
        try {
            const response = await this.client.post('/ai/completions', {
                prefix,
                context,
                language
            });
            return response.data.suggestions;
        }
        catch (error) {
            console.error('Error getting completions:', error);
            return [];
        }
    }
    async sendChatMessage(message, conversationId) {
        try {
            const response = await this.client.post('/chat/', {
                message,
                conversationId
            });
            return response.data;
        }
        catch (error) {
            console.error('Error sending chat message:', error);
            throw new Error('Failed to send chat message');
        }
    }
    async healthCheck() {
        try {
            await this.client.get('/health');
            return true;
        }
        catch (error) {
            return false;
        }
    }
}
exports.APIClient = APIClient;
//# sourceMappingURL=APIClient.js.map