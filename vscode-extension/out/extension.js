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
            // Show analysis in a new document
            const analysisDoc = await vscode.workspace.openTextDocument({
                content: analysis,
                language: 'markdown'
            });
            vscode.window.showTextDocument(analysisDoc);
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
                vscode.window.showErrorMessage('No code selected');
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
                vscode.window.showErrorMessage('No code selected');
                return;
            }
            const tests = await augmentProvider.generateTests(selectedText, editor.document.languageId);
            // Create new test file
            const testDoc = await vscode.workspace.openTextDocument({
                content: tests,
                language: editor.document.languageId
            });
            vscode.window.showTextDocument(testDoc);
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
                vscode.window.showErrorMessage('No code selected');
                return;
            }
            const refactoredCode = await augmentProvider.refactorCode(selectedText, editor.document.languageId);
            // Replace selected text with refactored code
            const edit = new vscode.WorkspaceEdit();
            edit.replace(editor.document.uri, selection, refactoredCode);
            await vscode.workspace.applyEdit(edit);
        }),
        vscode.commands.registerCommand('augment-ai.openChat', () => {
            vscode.commands.executeCommand('workbench.view.extension.augment-ai');
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
            const linePrefix = document.lineAt(position).text.substr(0, position.character);
            const codeContext = await codeAnalyzer.getCompletionContext(document, position);
            const suggestions = await augmentProvider.getCompletions(linePrefix, codeContext, document.languageId);
            return suggestions.map(suggestion => {
                const item = new vscode.CompletionItem(suggestion.text, vscode.CompletionItemKind.Text);
                item.detail = suggestion.description;
                item.insertText = suggestion.text;
                return item;
            });
        }
    }, '.' // Trigger on dot
    );
    context.subscriptions.push(completionProvider);
}
exports.activate = activate;
function deactivate() {
    console.log('Augment AI Replica is now deactivated');
}
exports.deactivate = deactivate;
//# sourceMappingURL=extension.js.map