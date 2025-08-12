"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ChatViewProvider = void 0;
const vscode = require("vscode");
class ChatViewProvider {
    constructor(_extensionUri, apiClient) {
        this._extensionUri = _extensionUri;
        this.apiClient = apiClient;
    }
    resolveWebviewView(webviewView, context, _token) {
        this._view = webviewView;
        webviewView.webview.options = {
            enableScripts: true,
            localResourceRoots: [this._extensionUri]
        };
        webviewView.webview.html = this._getHtmlForWebview(webviewView.webview);
        webviewView.webview.onDidReceiveMessage(async (data) => {
            switch (data.type) {
                case 'sendMessage':
                    await this.handleSendMessage(data.message);
                    break;
                case 'clearChat':
                    this.conversationId = undefined;
                    this.sendMessageToWebview({ type: 'chatCleared' });
                    break;
                case 'insertCode':
                    await this.insertCodeIntoEditor(data.code);
                    break;
            }
        });
    }
    async handleSendMessage(message) {
        try {
            // Show user message immediately
            this.sendMessageToWebview({
                type: 'userMessage',
                message: message,
                timestamp: new Date().toISOString()
            });
            // Show typing indicator
            this.sendMessageToWebview({ type: 'typing', isTyping: true });
            // Get AI response
            const response = await this.apiClient.sendChatMessage(message, this.conversationId);
            this.conversationId = response.conversationId;
            // Hide typing indicator and show AI response
            this.sendMessageToWebview({ type: 'typing', isTyping: false });
            this.sendMessageToWebview({
                type: 'aiMessage',
                message: response.response,
                timestamp: new Date().toISOString()
            });
        }
        catch (error) {
            console.error('Error sending chat message:', error);
            this.sendMessageToWebview({ type: 'typing', isTyping: false });
            this.sendMessageToWebview({
                type: 'error',
                message: 'Failed to send message. Please check your connection and try again.'
            });
        }
    }
    async insertCodeIntoEditor(code) {
        const editor = vscode.window.activeTextEditor;
        if (editor) {
            const position = editor.selection.active;
            await editor.edit(editBuilder => {
                editBuilder.insert(position, code);
            });
        }
        else {
            // Create new untitled document with the code
            const document = await vscode.workspace.openTextDocument({
                content: code,
                language: 'plaintext'
            });
            await vscode.window.showTextDocument(document);
        }
    }
    sendMessageToWebview(message) {
        if (this._view) {
            this._view.webview.postMessage(message);
        }
    }
    _getHtmlForWebview(webview) {
        return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Augment AI Chat</title>
    <style>
        body {
            font-family: var(--vscode-font-family);
            font-size: var(--vscode-font-size);
            color: var(--vscode-foreground);
            background-color: var(--vscode-editor-background);
            margin: 0;
            padding: 10px;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .chat-container {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 8px 12px;
            border-radius: 8px;
            max-width: 90%;
        }
        
        .user-message {
            background-color: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
            margin-left: auto;
            text-align: right;
        }
        
        .ai-message {
            background-color: var(--vscode-input-background);
            border: 1px solid var(--vscode-input-border);
        }
        
        .message-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .code-block {
            background-color: var(--vscode-textCodeBlock-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
            padding: 8px;
            margin: 8px 0;
            font-family: var(--vscode-editor-font-family);
            font-size: var(--vscode-editor-font-size);
            overflow-x: auto;
            position: relative;
        }
        
        .code-actions {
            position: absolute;
            top: 4px;
            right: 4px;
        }
        
        .code-action-btn {
            background: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
            border: none;
            padding: 2px 6px;
            border-radius: 2px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 4px;
        }
        
        .code-action-btn:hover {
            background: var(--vscode-button-hoverBackground);
        }
        
        .timestamp {
            font-size: 11px;
            color: var(--vscode-descriptionForeground);
            margin-top: 4px;
        }
        
        .input-container {
            display: flex;
            gap: 8px;
        }
        
        .message-input {
            flex: 1;
            padding: 8px;
            border: 1px solid var(--vscode-input-border);
            border-radius: 4px;
            background-color: var(--vscode-input-background);
            color: var(--vscode-input-foreground);
            font-family: inherit;
            font-size: inherit;
        }
        
        .send-button, .clear-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
        }
        
        .send-button {
            background-color: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
        }
        
        .send-button:hover {
            background-color: var(--vscode-button-hoverBackground);
        }
        
        .send-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .clear-button {
            background-color: var(--vscode-button-secondaryBackground);
            color: var(--vscode-button-secondaryForeground);
        }
        
        .clear-button:hover {
            background-color: var(--vscode-button-secondaryHoverBackground);
        }
        
        .typing-indicator {
            display: none;
            color: var(--vscode-descriptionForeground);
            font-style: italic;
            margin-bottom: 10px;
        }
        
        .error-message {
            background-color: var(--vscode-inputValidation-errorBackground);
            border: 1px solid var(--vscode-inputValidation-errorBorder);
            color: var(--vscode-errorForeground);
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="chat-container" id="chatContainer">
        <div class="ai-message message">
            <div class="message-content">Hello! I'm your AI coding assistant. I can help you with code analysis, explanations, refactoring, and more. What would you like to work on today?</div>
        </div>
    </div>
    
    <div class="typing-indicator" id="typingIndicator">AI is typing...</div>
    
    <div class="input-container">
        <input type="text" class="message-input" id="messageInput" placeholder="Ask me anything about your code..." />
        <button class="send-button" id="sendButton">Send</button>
        <button class="clear-button" id="clearButton">Clear</button>
    </div>

    <script>
        const vscode = acquireVsCodeApi();
        const chatContainer = document.getElementById('chatContainer');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const clearButton = document.getElementById('clearButton');
        const typingIndicator = document.getElementById('typingIndicator');

        function sendMessage() {
            const message = messageInput.value.trim();
            if (message) {
                vscode.postMessage({
                    type: 'sendMessage',
                    message: message
                });
                messageInput.value = '';
                sendButton.disabled = true;
            }
        }

        function clearChat() {
            chatContainer.innerHTML = '';
            vscode.postMessage({ type: 'clearChat' });
        }

        function insertCode(code) {
            vscode.postMessage({
                type: 'insertCode',
                code: code
            });
        }

        function addMessage(content, isUser = false, timestamp = null) {
            const messageDiv = document.createElement('div');
            messageDiv.className = \`message \${isUser ? 'user-message' : 'ai-message'}\`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            
            // Process code blocks
            const processedContent = content.replace(/\`\`\`([\\s\\S]*?)\`\`\`/g, (match, code) => {
                const codeId = 'code_' + Math.random().toString(36).substr(2, 9);
                return \`<div class="code-block">
                    <div class="code-actions">
                        <button class="code-action-btn" onclick="insertCode(\\\`\${code.trim()}\\\`)">Insert</button>
                        <button class="code-action-btn" onclick="navigator.clipboard.writeText(\\\`\${code.trim()}\\\`)">Copy</button>
                    </div>
                    <pre><code>\${code.trim()}</code></pre>
                </div>\`;
            });
            
            contentDiv.innerHTML = processedContent;
            messageDiv.appendChild(contentDiv);
            
            if (timestamp) {
                const timestampDiv = document.createElement('div');
                timestampDiv.className = 'timestamp';
                timestampDiv.textContent = new Date(timestamp).toLocaleTimeString();
                messageDiv.appendChild(timestampDiv);
            }
            
            chatContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            chatContainer.appendChild(errorDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        sendButton.addEventListener('click', sendMessage);
        clearButton.addEventListener('click', clearChat);

        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        messageInput.addEventListener('input', () => {
            sendButton.disabled = !messageInput.value.trim();
        });

        // Handle messages from extension
        window.addEventListener('message', event => {
            const message = event.data;
            
            switch (message.type) {
                case 'userMessage':
                    addMessage(message.message, true, message.timestamp);
                    break;
                case 'aiMessage':
                    addMessage(message.message, false, message.timestamp);
                    sendButton.disabled = false;
                    break;
                case 'typing':
                    typingIndicator.style.display = message.isTyping ? 'block' : 'none';
                    break;
                case 'error':
                    showError(message.message);
                    sendButton.disabled = false;
                    break;
                case 'chatCleared':
                    chatContainer.innerHTML = '';
                    addMessage('Chat cleared. How can I help you?', false);
                    break;
            }
        });

        // Initial state
        sendButton.disabled = true;
    </script>
</body>
</html>`;
    }
}
exports.ChatViewProvider = ChatViewProvider;
ChatViewProvider.viewType = 'augment-ai-chat';
//# sourceMappingURL=ChatViewProvider.js.map