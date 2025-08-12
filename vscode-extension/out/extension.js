"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.deactivate = exports.activate = void 0;
const vscode = require("vscode");
const CodasisProvider_1 = require("./providers/CodasisProvider");
const ChatViewProvider_1 = require("./providers/ChatViewProvider");
const ContextExplorerProvider_1 = require("./providers/ContextExplorerProvider");
const ProjectInsightsProvider_1 = require("./providers/ProjectInsightsProvider");
const ModelManagerProvider_1 = require("./providers/ModelManagerProvider");
const InlineCompletionProvider_1 = require("./providers/InlineCompletionProvider");
const HoverProvider_1 = require("./providers/HoverProvider");
const CodeLensProvider_1 = require("./providers/CodeLensProvider");
const CodeAnalyzer_1 = require("./services/CodeAnalyzer");
const APIClient_1 = require("./services/APIClient");
function activate(context) {
    console.log('🧠 Codasis is now active!');
    // Initialize services
    const apiClient = new APIClient_1.APIClient();
    const codeAnalyzer = new CodeAnalyzer_1.CodeAnalyzer();
    const codasisProvider = new CodasisProvider_1.CodasisProvider(apiClient, codeAnalyzer);
    // Initialize view providers
    const chatProvider = new ChatViewProvider_1.ChatViewProvider(context.extensionUri, apiClient);
    const contextProvider = new ContextExplorerProvider_1.ContextExplorerProvider(apiClient, codeAnalyzer);
    const insightsProvider = new ProjectInsightsProvider_1.ProjectInsightsProvider(apiClient, codeAnalyzer);
    const modelProvider = new ModelManagerProvider_1.ModelManagerProvider(apiClient);
    // Initialize inline providers
    const inlineProvider = new InlineCompletionProvider_1.InlineCompletionProvider(apiClient, codeAnalyzer);
    const hoverProvider = new HoverProvider_1.HoverProvider(apiClient, codeAnalyzer);
    const codeLensProvider = new CodeLensProvider_1.CodeLensProvider(apiClient, codeAnalyzer);
    // Register view providers
    context.subscriptions.push(vscode.window.registerWebviewViewProvider('codasis-chat', chatProvider), vscode.window.registerTreeDataProvider('codasis-context', contextProvider), vscode.window.registerTreeDataProvider('codasis-insights', insightsProvider), vscode.window.registerTreeDataProvider('codasis-models', modelProvider));
    // Register inline providers
    context.subscriptions.push(vscode.languages.registerInlineCompletionItemProvider({ scheme: 'file' }, inlineProvider), vscode.languages.registerHoverProvider({ scheme: 'file' }, hoverProvider), vscode.languages.registerCodeLensProvider({ scheme: 'file' }, codeLensProvider));
    // Register commands
    const commands = [
        vscode.commands.registerCommand('codasis.askQuestion', async () => {
            const question = await vscode.window.showInputBox({
                prompt: 'Ask AI a question about your code',
                placeHolder: 'How can I improve this function?',
                ignoreFocusOut: true
            });
            if (question) {
                const context = await codeAnalyzer.getCurrentContext();
                const response = await codasisProvider.askQuestion(question, context);
                // Show response in output channel
                const outputChannel = vscode.window.createOutputChannel('Codasis Response');
                outputChannel.clear();
                outputChannel.appendLine('🧠 Codasis AI Response:');
                outputChannel.appendLine('='.repeat(50));
                outputChannel.appendLine(response);
                outputChannel.show();
            }
        }),
        vscode.commands.registerCommand('codasis.analyzeCode', async (uri) => {
            const document = await vscode.workspace.openTextDocument(uri);
            const analysis = await codasisProvider.analyzeCode(document.getText(), document.languageId);
            // Show analysis in output channel
            const outputChannel = vscode.window.createOutputChannel('Codasis Analysis');
            outputChannel.clear();
            outputChannel.appendLine('📊 Code Analysis Report:');
            outputChannel.appendLine('='.repeat(50));
            outputChannel.appendLine(analysis);
            outputChannel.show();
        }),
        vscode.commands.registerCommand('codasis.explainCode', async () => {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                vscode.window.showErrorMessage('No active editor');
                return;
            }
            const selection = editor.selection;
            const selectedText = editor.document.getText(selection);
            if (!selectedText) {
                vscode.window.showErrorMessage('Please select some code to explain');
                return;
            }
            const explanation = await codasisProvider.explainCode(selectedText, editor.document.languageId);
            // Show explanation in webview panel
            const panel = vscode.window.createWebviewPanel('codasisExplanation', 'Code Explanation', vscode.ViewColumn.Beside, { enableScripts: true });
            panel.webview.html = getExplanationWebviewContent(explanation, selectedText, editor.document.languageId);
        }),
        vscode.commands.registerCommand('codasis.generateTests', async () => {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                vscode.window.showErrorMessage('No active editor');
                return;
            }
            const selection = editor.selection;
            const selectedText = editor.document.getText(selection);
            if (!selectedText) {
                vscode.window.showErrorMessage('Please select some code to generate tests for');
                return;
            }
            vscode.window.withProgress({
                location: vscode.ProgressLocation.Notification,
                title: "🧪 Generating tests...",
                cancellable: false
            }, async (progress) => {
                progress.report({ increment: 0, message: "Analyzing code structure..." });
                const tests = await codasisProvider.generateTests(selectedText, editor.document.languageId);
                progress.report({ increment: 100, message: "Tests generated!" });
                // Create new document with tests
                const testDocument = await vscode.workspace.openTextDocument({
                    content: tests,
                    language: editor.document.languageId
                });
                await vscode.window.showTextDocument(testDocument, vscode.ViewColumn.Beside);
            });
        }),
        vscode.commands.registerCommand('augment-ai.refactorCode', async () => {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                vscode.window.showErrorMessage('No active editor');
                return;
            }
            const selection = editor.selection;
            const selectedText = editor.document.getText(selection);
            if (!selectedText) {
                vscode.window.showErrorMessage('Please select some code to refactor');
                return;
            }
            const refactoredCode = await codasisProvider.refactorCode(selectedText, editor.document.languageId);
            // Replace selected text with refactored code
            await editor.edit(editBuilder => {
                editBuilder.replace(selection, refactoredCode);
            });
        }),
        vscode.commands.registerCommand('codasis.openChat', () => {
            vscode.commands.executeCommand('workbench.view.extension.codasis');
        }),
        vscode.commands.registerCommand('codasis.openContextPanel', () => {
            vscode.commands.executeCommand('codasis-context.focus');
        }),
        vscode.commands.registerCommand('codasis.showProjectInsights', () => {
            vscode.commands.executeCommand('codasis-insights.focus');
        }),
        vscode.commands.registerCommand('codasis.toggleInlineSuggestions', () => {
            const config = vscode.workspace.getConfiguration('codasis');
            const current = config.get('inlineSuggestions', true);
            config.update('inlineSuggestions', !current, vscode.ConfigurationTarget.Global);
            vscode.window.showInformationMessage(`Inline suggestions ${!current ? 'enabled' : 'disabled'}`);
        }),
        vscode.commands.registerCommand('codasis.indexWorkspace', async () => {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (!workspaceFolders || workspaceFolders.length === 0) {
                vscode.window.showErrorMessage('No workspace folder is open');
                return;
            }
            const workspacePath = workspaceFolders[0].uri.fsPath;
            // Show progress
            vscode.window.withProgress({
                location: vscode.ProgressLocation.Notification,
                title: "Indexing workspace for AI context...",
                cancellable: false
            }, async (progress) => {
                try {
                    progress.report({ increment: 0, message: "Analyzing project structure..." });
                    const result = await apiClient.indexWorkspace(workspacePath);
                    progress.report({ increment: 100, message: "Indexing complete!" });
                    vscode.window.showInformationMessage(`Workspace indexed successfully! Processed ${result.stats.files_processed} files, ` +
                        `updated ${result.stats.files_updated} files.`);
                }
                catch (error) {
                    console.error('Workspace indexing failed:', error);
                    vscode.window.showErrorMessage(`Failed to index workspace: ${error.message}`);
                }
            });
        }),
        vscode.commands.registerCommand('codasis.smartAnalyze', async () => {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                vscode.window.showErrorMessage('No active editor');
                return;
            }
            const document = editor.document;
            const selection = editor.selection;
            const selectedText = document.getText(selection);
            if (!selectedText) {
                vscode.window.showErrorMessage('Please select some code to analyze');
                return;
            }
            try {
                vscode.window.withProgress({
                    location: vscode.ProgressLocation.Notification,
                    title: "Performing intelligent code analysis...",
                    cancellable: false
                }, async (progress) => {
                    progress.report({ increment: 0, message: "Gathering context..." });
                    const analysis = await codasisProvider.smartAnalyzeCode(selectedText, document.languageId, {
                        focus_area: 'general',
                        include_tests: false
                    });
                    progress.report({ increment: 100, message: "Analysis complete!" });
                    // Show analysis in webview panel
                    const panel = vscode.window.createWebviewPanel('codasisSmartAnalysis', '🧠 Smart Analysis', vscode.ViewColumn.Beside, { enableScripts: true });
                    panel.webview.html = getAnalysisWebviewContent(analysis, selectedText, document.languageId);
                });
            }
            catch (error) {
                console.error('Smart analysis failed:', error);
                vscode.window.showErrorMessage(`Analysis failed: ${error.message}`);
            }
        }),
        // Code lens commands
        vscode.commands.registerCommand('codasis.explainFunction', async (uri, functionName, line) => {
            try {
                const document = await vscode.workspace.openTextDocument(uri);
                const functionCode = extractFunctionCode(document, functionName, line);
                const explanation = await codasisProvider.explainCode(functionCode, document.languageId);
                const panel = vscode.window.createWebviewPanel('codasisFunctionExplanation', `Function: ${functionName}`, vscode.ViewColumn.Beside, { enableScripts: true });
                panel.webview.html = getExplanationWebviewContent(explanation, functionCode, document.languageId);
            }
            catch (error) {
                vscode.window.showErrorMessage(`Failed to explain function: ${error.message}`);
            }
        }),
        vscode.commands.registerCommand('codasis.generateTestsForFunction', async (uri, functionName, line) => {
            try {
                const document = await vscode.workspace.openTextDocument(uri);
                const functionCode = extractFunctionCode(document, functionName, line);
                const tests = await codasisProvider.generateTests(functionCode, document.languageId);
                const testDocument = await vscode.workspace.openTextDocument({
                    content: tests,
                    language: document.languageId
                });
                await vscode.window.showTextDocument(testDocument, vscode.ViewColumn.Beside);
            }
            catch (error) {
                vscode.window.showErrorMessage(`Failed to generate tests: ${error.message}`);
            }
        }),
        vscode.commands.registerCommand('codasis.switchModel', async (modelId) => {
            await modelProvider.switchModel(modelId);
        })
    ];
    context.subscriptions.push(...commands);
    // Set context for when extension is enabled
    vscode.commands.executeCommand('setContext', 'codasis:enabled', true);
    // Register completion provider
    const completionProvider = vscode.languages.registerCompletionItemProvider({ scheme: 'file' }, {
        async provideCompletionItems(document, position, token, context) {
            const config = vscode.workspace.getConfiguration('codasis');
            if (!config.get('autoComplete')) {
                return [];
            }
            try {
                const suggestions = await codasisProvider.getCodeSuggestions(document, position);
                return suggestions;
            }
            catch (error) {
                console.error('Error getting completions:', error);
                return [];
            }
        }
    }, '.', '(', '[', '"', "'" // Multiple trigger characters
    );
    context.subscriptions.push(completionProvider);
    // Initialize workspace indexing on startup
    setTimeout(async () => {
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (workspaceFolders && workspaceFolders.length > 0) {
            try {
                await apiClient.indexWorkspace(workspaceFolders[0].uri.fsPath);
                console.log('🧠 Codasis: Workspace indexed successfully');
            }
            catch (error) {
                console.log('🧠 Codasis: Workspace indexing failed:', error);
            }
        }
    }, 2000);
}
exports.activate = activate;
// Helper function to extract function code
function extractFunctionCode(document, functionName, line) {
    const startLine = Math.max(0, line - 1);
    let endLine = startLine;
    // Simple function extraction - find the function and its closing brace
    const text = document.getText();
    const lines = text.split('\n');
    // Find the end of the function by counting braces
    let braceCount = 0;
    let foundStart = false;
    for (let i = startLine; i < lines.length; i++) {
        const currentLine = lines[i];
        if (!foundStart && currentLine.includes(functionName)) {
            foundStart = true;
        }
        if (foundStart) {
            // Count opening and closing braces
            for (const char of currentLine) {
                if (char === '{')
                    braceCount++;
                if (char === '}')
                    braceCount--;
            }
            endLine = i;
            // If we've closed all braces, we've found the end
            if (braceCount === 0 && foundStart) {
                break;
            }
        }
    }
    // Extract the function code
    const functionRange = new vscode.Range(startLine, 0, endLine, lines[endLine]?.length || 0);
    return document.getText(functionRange);
}
// Helper function for explanation webview
function getExplanationWebviewContent(explanation, code, language) {
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Explanation</title>
    <style>
        body {
            font-family: var(--vscode-font-family);
            color: var(--vscode-foreground);
            background-color: var(--vscode-editor-background);
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            border-bottom: 2px solid var(--vscode-panel-border);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .code-block {
            background-color: var(--vscode-textCodeBlock-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            overflow-x: auto;
        }
        .explanation {
            background-color: var(--vscode-input-background);
            border-left: 4px solid var(--vscode-button-background);
            padding: 15px;
            margin: 15px 0;
        }
        pre {
            margin: 0;
            font-family: var(--vscode-editor-font-family);
        }
        h1, h2, h3 {
            color: var(--vscode-textLink-foreground);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧠 Code Explanation</h1>
        <p>Language: <strong>${language}</strong></p>
    </div>

    <h2>📝 Original Code</h2>
    <div class="code-block">
        <pre><code>${code}</code></pre>
    </div>

    <h2>💡 AI Explanation</h2>
    <div class="explanation">
        ${explanation.replace(/\n/g, '<br>')}
    </div>
</body>
</html>`;
}
// Helper function for analysis webview
function getAnalysisWebviewContent(analysis, code, language) {
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Analysis</title>
    <style>
        body {
            font-family: var(--vscode-font-family);
            color: var(--vscode-foreground);
            background-color: var(--vscode-editor-background);
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            border-bottom: 2px solid var(--vscode-panel-border);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .code-block {
            background-color: var(--vscode-textCodeBlock-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            overflow-x: auto;
        }
        .analysis {
            background-color: var(--vscode-input-background);
            border-left: 4px solid var(--vscode-button-background);
            padding: 15px;
            margin: 15px 0;
        }
        .badge {
            display: inline-block;
            background-color: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-right: 8px;
        }
        pre {
            margin: 0;
            font-family: var(--vscode-editor-font-family);
        }
        h1, h2, h3 {
            color: var(--vscode-textLink-foreground);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧠 Smart Code Analysis</h1>
        <span class="badge">${language}</span>
        <span class="badge">Context-Aware</span>
    </div>

    <h2>📝 Analyzed Code</h2>
    <div class="code-block">
        <pre><code>${code}</code></pre>
    </div>

    <h2>🔍 AI Analysis</h2>
    <div class="analysis">
        ${analysis.replace(/\n/g, '<br>')}
    </div>
</body>
</html>`;
}
function deactivate() {
    console.log('🧠 Codasis deactivated');
}
exports.deactivate = deactivate;
//# sourceMappingURL=extension.js.map