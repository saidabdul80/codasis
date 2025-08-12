"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ContextExplorerProvider = void 0;
const vscode = require("vscode");
class ContextExplorerProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.contextData = null;
        // Refresh context when active editor changes
        vscode.window.onDidChangeActiveTextEditor(() => {
            this.refresh();
        });
        // Refresh context when document is saved
        vscode.workspace.onDidSaveTextDocument(() => {
            this.refresh();
        });
    }
    refresh() {
        this._onDidChangeTreeData.fire();
    }
    getTreeItem(element) {
        return element;
    }
    async getChildren(element) {
        if (!element) {
            // Root level items
            return [
                new ContextItem('Current File', vscode.TreeItemCollapsibleState.Expanded, 'current-file'),
                new ContextItem('Related Files', vscode.TreeItemCollapsibleState.Expanded, 'related-files'),
                new ContextItem('Dependencies', vscode.TreeItemCollapsibleState.Expanded, 'dependencies'),
                new ContextItem('Similar Code', vscode.TreeItemCollapsibleState.Expanded, 'similar-code'),
                new ContextItem('Project Context', vscode.TreeItemCollapsibleState.Expanded, 'project-context')
            ];
        }
        // Get context data if not already loaded
        if (!this.contextData) {
            await this.loadContextData();
        }
        switch (element.contextType) {
            case 'current-file':
                return this.getCurrentFileItems();
            case 'related-files':
                return this.getRelatedFileItems();
            case 'dependencies':
                return this.getDependencyItems();
            case 'similar-code':
                return this.getSimilarCodeItems();
            case 'project-context':
                return this.getProjectContextItems();
            default:
                return [];
        }
    }
    async loadContextData() {
        try {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                this.contextData = null;
                return;
            }
            // Get current context from the API
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (workspaceFolders && workspaceFolders.length > 0) {
                // This would call the context retrieval service
                // For now, we'll use mock data
                this.contextData = {
                    current_file: {
                        file_path: editor.document.fileName,
                        language: editor.document.languageId,
                        functions: ['function1', 'function2'],
                        classes: ['Class1'],
                        imports: ['import1', 'import2']
                    },
                    related_files: [
                        { file_path: 'related1.js', relationship: 'imports_from' },
                        { file_path: 'related2.js', relationship: 'imports_to' }
                    ],
                    dependencies: [
                        { module: 'lodash', type: 'external' },
                        { module: './utils', type: 'local' }
                    ],
                    similar_code: [
                        { file_path: 'similar1.js', similarity_score: 0.85 },
                        { file_path: 'similar2.js', similarity_score: 0.72 }
                    ],
                    project_context: {
                        total_files: 127,
                        languages: ['JavaScript', 'TypeScript'],
                        frameworks: ['React']
                    }
                };
            }
        }
        catch (error) {
            console.error('Error loading context data:', error);
            this.contextData = null;
        }
    }
    getCurrentFileItems() {
        if (!this.contextData?.current_file) {
            return [new ContextItem('No active file', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const file = this.contextData.current_file;
        const items = [];
        items.push(new ContextItem(`📄 ${file.file_path.split('/').pop()}`, vscode.TreeItemCollapsibleState.None, 'file-info'));
        items.push(new ContextItem(`🔤 Language: ${file.language}`, vscode.TreeItemCollapsibleState.None, 'language-info'));
        if (file.functions?.length > 0) {
            items.push(new ContextItem(`⚡ Functions (${file.functions.length})`, vscode.TreeItemCollapsibleState.None, 'functions-info'));
        }
        if (file.classes?.length > 0) {
            items.push(new ContextItem(`🏗️ Classes (${file.classes.length})`, vscode.TreeItemCollapsibleState.None, 'classes-info'));
        }
        if (file.imports?.length > 0) {
            items.push(new ContextItem(`📦 Imports (${file.imports.length})`, vscode.TreeItemCollapsibleState.None, 'imports-info'));
        }
        return items;
    }
    getRelatedFileItems() {
        if (!this.contextData?.related_files?.length) {
            return [new ContextItem('No related files found', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        return this.contextData.related_files.map((file) => {
            const icon = file.relationship === 'imports_from' ? '📥' : '📤';
            const item = new ContextItem(`${icon} ${file.file_path.split('/').pop()}`, vscode.TreeItemCollapsibleState.None, 'related-file');
            item.tooltip = `${file.relationship}: ${file.file_path}`;
            item.command = {
                command: 'vscode.open',
                title: 'Open File',
                arguments: [vscode.Uri.file(file.file_path)]
            };
            return item;
        });
    }
    getDependencyItems() {
        if (!this.contextData?.dependencies?.length) {
            return [new ContextItem('No dependencies found', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        return this.contextData.dependencies.map((dep) => {
            const icon = dep.type === 'external' ? '🌐' : '📁';
            const item = new ContextItem(`${icon} ${dep.module}`, vscode.TreeItemCollapsibleState.None, 'dependency');
            item.tooltip = `${dep.type} dependency: ${dep.module}`;
            return item;
        });
    }
    getSimilarCodeItems() {
        if (!this.contextData?.similar_code?.length) {
            return [new ContextItem('No similar code found', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        return this.contextData.similar_code.map((similar) => {
            const confidence = Math.round(similar.similarity_score * 100);
            const item = new ContextItem(`🔍 ${similar.file_path.split('/').pop()} (${confidence}%)`, vscode.TreeItemCollapsibleState.None, 'similar-code');
            item.tooltip = `${confidence}% similarity: ${similar.file_path}`;
            item.command = {
                command: 'vscode.open',
                title: 'Open File',
                arguments: [vscode.Uri.file(similar.file_path)]
            };
            return item;
        });
    }
    getProjectContextItems() {
        if (!this.contextData?.project_context) {
            return [new ContextItem('No project context available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const context = this.contextData.project_context;
        const items = [];
        items.push(new ContextItem(`📊 Total Files: ${context.total_files}`, vscode.TreeItemCollapsibleState.None, 'project-info'));
        if (context.languages?.length > 0) {
            items.push(new ContextItem(`🔤 Languages: ${context.languages.join(', ')}`, vscode.TreeItemCollapsibleState.None, 'project-info'));
        }
        if (context.frameworks?.length > 0) {
            items.push(new ContextItem(`🏗️ Frameworks: ${context.frameworks.join(', ')}`, vscode.TreeItemCollapsibleState.None, 'project-info'));
        }
        return items;
    }
}
exports.ContextExplorerProvider = ContextExplorerProvider;
class ContextItem extends vscode.TreeItem {
    constructor(label, collapsibleState, contextType) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.contextType = contextType;
        this.contextValue = contextType;
    }
}
//# sourceMappingURL=ContextExplorerProvider.js.map