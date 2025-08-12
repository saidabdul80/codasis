"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ModelManagerProvider = void 0;
const vscode = require("vscode");
class ModelManagerProvider {
    constructor(apiClient) {
        this.apiClient = apiClient;
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.models = [];
        this.currentModel = '';
        this.loadModels();
        this.loadCurrentModel();
    }
    refresh() {
        this.loadModels();
        this._onDidChangeTreeData.fire();
    }
    getTreeItem(element) {
        return element;
    }
    async getChildren(element) {
        if (!element) {
            // Root level items
            const items = [];
            // Current model section
            items.push(new ModelItem(`🎯 Current: ${this.currentModel || 'None'}`, vscode.TreeItemCollapsibleState.None, 'current-model'));
            // Available models section
            items.push(new ModelItem('🤖 Available Models', vscode.TreeItemCollapsibleState.Expanded, 'models-header'));
            // Model performance section
            items.push(new ModelItem('📊 Performance', vscode.TreeItemCollapsibleState.Expanded, 'performance-header'));
            // Settings section
            items.push(new ModelItem('⚙️ Settings', vscode.TreeItemCollapsibleState.Expanded, 'settings-header'));
            return items;
        }
        switch (element.modelType) {
            case 'models-header':
                return this.getModelItems();
            case 'performance-header':
                return this.getPerformanceItems();
            case 'settings-header':
                return this.getSettingsItems();
            default:
                return [];
        }
    }
    async loadModels() {
        try {
            // In a real implementation, this would call the API
            this.models = [
                {
                    id: 'deepseek-r1',
                    name: 'DeepSeek R1',
                    provider: 'DeepSeek',
                    status: 'available',
                    speed: 'fast',
                    quality: 'high',
                    cost: 'low',
                    description: 'Advanced reasoning model with excellent code understanding'
                },
                {
                    id: 'gpt-4',
                    name: 'GPT-4',
                    provider: 'OpenAI',
                    status: 'available',
                    speed: 'medium',
                    quality: 'very-high',
                    cost: 'high',
                    description: 'Most capable model for complex reasoning and code generation'
                },
                {
                    id: 'claude-3',
                    name: 'Claude 3',
                    provider: 'Anthropic',
                    status: 'available',
                    speed: 'medium',
                    quality: 'high',
                    cost: 'medium',
                    description: 'Excellent for code analysis and explanation'
                },
                {
                    id: 'gemini-pro',
                    name: 'Gemini Pro',
                    provider: 'Google',
                    status: 'limited',
                    speed: 'fast',
                    quality: 'medium',
                    cost: 'low',
                    description: 'Good general-purpose model with multimodal capabilities'
                }
            ];
        }
        catch (error) {
            console.error('Error loading models:', error);
            this.models = [];
        }
    }
    loadCurrentModel() {
        const config = vscode.workspace.getConfiguration('codasis');
        this.currentModel = config.get('preferredModel', 'deepseek-r1');
    }
    getModelItems() {
        return this.models.map(model => {
            const statusIcon = model.status === 'available' ? '🟢' :
                model.status === 'limited' ? '🟡' : '🔴';
            const isActive = model.id === this.currentModel;
            const activeIcon = isActive ? '✅ ' : '';
            const item = new ModelItem(`${activeIcon}${statusIcon} ${model.name}`, vscode.TreeItemCollapsibleState.None, 'model');
            item.tooltip = `${model.description}\nProvider: ${model.provider}\nStatus: ${model.status}`;
            item.description = model.provider;
            // Add command to switch model
            item.command = {
                command: 'codasis.switchModel',
                title: 'Switch Model',
                arguments: [model.id]
            };
            return item;
        });
    }
    getPerformanceItems() {
        const currentModelData = this.models.find(m => m.id === this.currentModel);
        if (!currentModelData) {
            return [new ModelItem('No model selected', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const items = [];
        // Speed indicator
        const speedIcon = currentModelData.speed === 'fast' ? '🚀' :
            currentModelData.speed === 'medium' ? '⚡' : '🐌';
        items.push(new ModelItem(`${speedIcon} Speed: ${currentModelData.speed}`, vscode.TreeItemCollapsibleState.None, 'performance'));
        // Quality indicator
        const qualityIcon = currentModelData.quality === 'very-high' ? '🌟' :
            currentModelData.quality === 'high' ? '⭐' : '✨';
        items.push(new ModelItem(`${qualityIcon} Quality: ${currentModelData.quality}`, vscode.TreeItemCollapsibleState.None, 'performance'));
        // Cost indicator
        const costIcon = currentModelData.cost === 'low' ? '💚' :
            currentModelData.cost === 'medium' ? '💛' : '💰';
        items.push(new ModelItem(`${costIcon} Cost: ${currentModelData.cost}`, vscode.TreeItemCollapsibleState.None, 'performance'));
        return items;
    }
    getSettingsItems() {
        const config = vscode.workspace.getConfiguration('codasis');
        const items = [];
        // Auto-complete setting
        const autoComplete = config.get('autoComplete', true);
        items.push(new ModelItem(`🔄 Auto-complete: ${autoComplete ? 'On' : 'Off'}`, vscode.TreeItemCollapsibleState.None, 'setting'));
        // Inline suggestions setting
        const inlineSuggestions = config.get('inlineSuggestions', true);
        items.push(new ModelItem(`💡 Inline suggestions: ${inlineSuggestions ? 'On' : 'Off'}`, vscode.TreeItemCollapsibleState.None, 'setting'));
        // Context depth setting
        const contextDepth = config.get('contextDepth', 5);
        items.push(new ModelItem(`📊 Context depth: ${contextDepth}`, vscode.TreeItemCollapsibleState.None, 'setting'));
        // Hover info setting
        const hoverInfo = config.get('enableHoverInfo', true);
        items.push(new ModelItem(`ℹ️ Hover info: ${hoverInfo ? 'On' : 'Off'}`, vscode.TreeItemCollapsibleState.None, 'setting'));
        return items;
    }
    async switchModel(modelId) {
        try {
            const config = vscode.workspace.getConfiguration('codasis');
            await config.update('preferredModel', modelId, vscode.ConfigurationTarget.Global);
            this.currentModel = modelId;
            this._onDidChangeTreeData.fire();
            const model = this.models.find(m => m.id === modelId);
            vscode.window.showInformationMessage(`Switched to ${model?.name || modelId}`);
        }
        catch (error) {
            vscode.window.showErrorMessage(`Failed to switch model: ${error}`);
        }
    }
}
exports.ModelManagerProvider = ModelManagerProvider;
class ModelItem extends vscode.TreeItem {
    constructor(label, collapsibleState, modelType) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.modelType = modelType;
        this.contextValue = modelType;
    }
}
//# sourceMappingURL=ModelManagerProvider.js.map