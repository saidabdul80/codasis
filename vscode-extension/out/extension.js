"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.deactivate = exports.activate = void 0;
const vscode = require("vscode");
const AugmentAIProvider_1 = require("./providers/AugmentAIProvider");
const ChatViewProvider_1 = require("./providers/ChatViewProvider");
const CodeAnalyzer_1 = require("./services/CodeAnalyzer");
const APIClient_1 = require("./services/APIClient");
function activate(context) {
    console.log('Augment AI Replica is now active!');
    // Initialize services
    const apiClient = new APIClient_1.APIClient();
    const codeAnalyzer = new CodeAnalyzer_1.CodeAnalyzer();
    const augmentProvider = new AugmentAIProvider_1.AugmentAIProvider(apiClient, codeAnalyzer);
    const chatProvider = new ChatViewProvider_1.ChatViewProvider(context.extensionUri, apiClient);
    // Register chat view
    context.subscriptions.push(vscode.window.registerWebviewViewProvider('augment-ai-chat', chatProvider));
    // Register commands
    const commands = [
        vscode.commands.registerCommand('augment-ai.askQuestion', async () => {
            const question = await vscode.window.showInputBox({
                prompt: 'Ask AI a question about your code',
                placeHolder: 'How can I improve this function?'
            });
            if (question) {
                const context = await codeAnalyzer.getCurrentContext();
                const response = await augmentProvider.askQuestion(question, context);
                vscode.window.showInformationMessage(response);
            }
        }),
        vscode.commands.registerCommand('augment-ai.analyzeCode', async (uri) => {
            const document = await vscode.workspace.openTextDocument(uri);
            const analysis = await augmentProvider.analyzeCode(document.getText(), document.languageId);
            // Show analysis in output channel
            const outputChannel = vscode.window.createOutputChannel('Augment AI Analysis');
            outputChannel.clear();
            outputChannel.appendLine('Code Analysis Report:');
            outputChannel.appendLine('===================');
            outputChannel.appendLine(analysis);
            outputChannel.show();
        }),
        vscode.commands.registerCommand('augment-ai.explainCode', async () => {
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
            const explanation = await augmentProvider.explainCode(selectedText, editor.document.languageId);
            // Show explanation in output channel
            const outputChannel = vscode.window.createOutputChannel('Augment AI Explanation');
            outputChannel.clear();
            outputChannel.appendLine('Code Explanation:');
            outputChannel.appendLine('================');
            outputChannel.appendLine(explanation);
            outputChannel.show();
        }),
        vscode.commands.registerCommand('augment-ai.generateTests', async () => {
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
            const tests = await augmentProvider.generateTests(selectedText, editor.document.languageId);
            // Create new document with tests
            const testDocument = await vscode.workspace.openTextDocument({
                content: tests,
                language: editor.document.languageId
            });
            await vscode.window.showTextDocument(testDocument);
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
            const refactoredCode = await augmentProvider.refactorCode(selectedText, editor.document.languageId);
            // Replace selected text with refactored code
            await editor.edit(editBuilder => {
                editBuilder.replace(selection, refactoredCode);
            });
        }),
        vscode.commands.registerCommand('augment-ai.openChat', () => {
            vscode.commands.executeCommand('workbench.view.extension.augment-ai');
        }),
        vscode.commands.registerCommand('augment-ai.indexWorkspace', async () => {
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
        vscode.commands.registerCommand('augment-ai.smartAnalyze', async () => {
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
                    const analysis = await augmentProvider.smartAnalyzeCode(selectedText, document.languageId, {
                        focus_area: 'general',
                        include_tests: false
                    });
                    progress.report({ increment: 100, message: "Analysis complete!" });
                    // Show analysis in output channel
                    const outputChannel = vscode.window.createOutputChannel('Augment AI Smart Analysis');
                    outputChannel.clear();
                    outputChannel.appendLine('Smart Code Analysis:');
                    outputChannel.appendLine('===================');
                    outputChannel.appendLine(analysis);
                    outputChannel.show();
                });
            }
            catch (error) {
                console.error('Smart analysis failed:', error);
                vscode.window.showErrorMessage(`Analysis failed: ${error.message}`);
            }
        })
    ];
    context.subscriptions.push(...commands);
    // Set context for when extension is enabled
    vscode.commands.executeCommand('setContext', 'augment-ai:enabled', true);
    // Register completion provider
    const completionProvider = vscode.languages.registerCompletionItemProvider({ scheme: 'file' }, {
        async provideCompletionItems(document, position, token, context) {
            const config = vscode.workspace.getConfiguration('augment-ai');
            if (!config.get('autoComplete')) {
                return [];
            }
            try {
                const suggestions = await augmentProvider.getCodeSuggestions(document, position);
                return suggestions;
            }
            catch (error) {
                console.error('Error getting completions:', error);
                return [];
            }
        }
    }, '.' // Trigger on dot
    );
    context.subscriptions.push(completionProvider);
}
exports.activate = activate;
function deactivate() { }
exports.deactivate = deactivate;
//# sourceMappingURL=extension.js.map