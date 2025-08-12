"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ProjectInsightsProvider = void 0;
const vscode = require("vscode");
class ProjectInsightsProvider {
    constructor(apiClient, codeAnalyzer) {
        this.apiClient = apiClient;
        this.codeAnalyzer = codeAnalyzer;
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.insights = null;
        // Refresh insights periodically
        setInterval(() => {
            this.refresh();
        }, 30000); // Every 30 seconds
    }
    refresh() {
        this.insights = null;
        this._onDidChangeTreeData.fire();
    }
    getTreeItem(element) {
        return element;
    }
    async getChildren(element) {
        if (!element) {
            // Root level categories
            return [
                new InsightItem('📊 Code Quality', vscode.TreeItemCollapsibleState.Expanded, 'code-quality'),
                new InsightItem('🚀 Performance', vscode.TreeItemCollapsibleState.Expanded, 'performance'),
                new InsightItem('🔒 Security', vscode.TreeItemCollapsibleState.Expanded, 'security'),
                new InsightItem('🧪 Testing', vscode.TreeItemCollapsibleState.Expanded, 'testing'),
                new InsightItem('📈 Metrics', vscode.TreeItemCollapsibleState.Expanded, 'metrics'),
                new InsightItem('💡 Suggestions', vscode.TreeItemCollapsibleState.Expanded, 'suggestions')
            ];
        }
        // Load insights if not already loaded
        if (!this.insights) {
            await this.loadInsights();
        }
        switch (element.insightType) {
            case 'code-quality':
                return this.getCodeQualityItems();
            case 'performance':
                return this.getPerformanceItems();
            case 'security':
                return this.getSecurityItems();
            case 'testing':
                return this.getTestingItems();
            case 'metrics':
                return this.getMetricsItems();
            case 'suggestions':
                return this.getSuggestionItems();
            default:
                return [];
        }
    }
    async loadInsights() {
        try {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (!workspaceFolders || workspaceFolders.length === 0) {
                this.insights = null;
                return;
            }
            // In a real implementation, this would call the backend API
            // For now, we'll use mock data
            this.insights = {
                codeQuality: {
                    score: 85,
                    issues: [
                        { type: 'complexity', count: 3, severity: 'medium' },
                        { type: 'duplication', count: 1, severity: 'low' }
                    ]
                },
                performance: {
                    score: 78,
                    issues: [
                        { type: 'inefficient-loops', count: 2, severity: 'medium' },
                        { type: 'memory-leaks', count: 1, severity: 'high' }
                    ]
                },
                security: {
                    score: 92,
                    issues: [
                        { type: 'input-validation', count: 1, severity: 'medium' }
                    ]
                },
                testing: {
                    coverage: 67,
                    testFiles: 15,
                    totalFiles: 45
                },
                metrics: {
                    totalLines: 12450,
                    totalFiles: 127,
                    avgComplexity: 4.2,
                    techDebt: '2.5 days'
                },
                suggestions: [
                    { type: 'refactor', description: 'Extract common utility functions', priority: 'high' },
                    { type: 'optimize', description: 'Implement caching for API calls', priority: 'medium' },
                    { type: 'test', description: 'Add unit tests for user service', priority: 'high' }
                ]
            };
        }
        catch (error) {
            console.error('Error loading insights:', error);
            this.insights = null;
        }
    }
    getCodeQualityItems() {
        if (!this.insights?.codeQuality) {
            return [new InsightItem('No data available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const quality = this.insights.codeQuality;
        const items = [];
        // Overall score
        const scoreIcon = quality.score >= 80 ? '🟢' : quality.score >= 60 ? '🟡' : '🔴';
        items.push(new InsightItem(`${scoreIcon} Overall Score: ${quality.score}/100`, vscode.TreeItemCollapsibleState.None, 'score'));
        // Issues breakdown
        if (quality.issues?.length > 0) {
            quality.issues.forEach((issue) => {
                const severityIcon = issue.severity === 'high' ? '🔴' : issue.severity === 'medium' ? '🟡' : '🟢';
                items.push(new InsightItem(`${severityIcon} ${issue.type}: ${issue.count} issues`, vscode.TreeItemCollapsibleState.None, 'issue'));
            });
        }
        return items;
    }
    getPerformanceItems() {
        if (!this.insights?.performance) {
            return [new InsightItem('No data available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const perf = this.insights.performance;
        const items = [];
        // Overall score
        const scoreIcon = perf.score >= 80 ? '🟢' : perf.score >= 60 ? '🟡' : '🔴';
        items.push(new InsightItem(`${scoreIcon} Performance Score: ${perf.score}/100`, vscode.TreeItemCollapsibleState.None, 'score'));
        // Performance issues
        if (perf.issues?.length > 0) {
            perf.issues.forEach((issue) => {
                const severityIcon = issue.severity === 'high' ? '🔴' : issue.severity === 'medium' ? '🟡' : '🟢';
                items.push(new InsightItem(`${severityIcon} ${issue.type}: ${issue.count} issues`, vscode.TreeItemCollapsibleState.None, 'issue'));
            });
        }
        return items;
    }
    getSecurityItems() {
        if (!this.insights?.security) {
            return [new InsightItem('No data available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const security = this.insights.security;
        const items = [];
        // Overall score
        const scoreIcon = security.score >= 90 ? '🟢' : security.score >= 70 ? '🟡' : '🔴';
        items.push(new InsightItem(`${scoreIcon} Security Score: ${security.score}/100`, vscode.TreeItemCollapsibleState.None, 'score'));
        // Security issues
        if (security.issues?.length > 0) {
            security.issues.forEach((issue) => {
                const severityIcon = issue.severity === 'high' ? '🔴' : issue.severity === 'medium' ? '🟡' : '🟢';
                items.push(new InsightItem(`${severityIcon} ${issue.type}: ${issue.count} issues`, vscode.TreeItemCollapsibleState.None, 'issue'));
            });
        }
        else {
            items.push(new InsightItem('✅ No security issues found', vscode.TreeItemCollapsibleState.None, 'info'));
        }
        return items;
    }
    getTestingItems() {
        if (!this.insights?.testing) {
            return [new InsightItem('No data available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const testing = this.insights.testing;
        const items = [];
        // Coverage
        const coverageIcon = testing.coverage >= 80 ? '🟢' : testing.coverage >= 60 ? '🟡' : '🔴';
        items.push(new InsightItem(`${coverageIcon} Test Coverage: ${testing.coverage}%`, vscode.TreeItemCollapsibleState.None, 'coverage'));
        // Test files
        items.push(new InsightItem(`🧪 Test Files: ${testing.testFiles}/${testing.totalFiles}`, vscode.TreeItemCollapsibleState.None, 'test-files'));
        return items;
    }
    getMetricsItems() {
        if (!this.insights?.metrics) {
            return [new InsightItem('No data available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        const metrics = this.insights.metrics;
        const items = [];
        items.push(new InsightItem(`📄 Total Lines: ${metrics.totalLines.toLocaleString()}`, vscode.TreeItemCollapsibleState.None, 'metric'));
        items.push(new InsightItem(`📁 Total Files: ${metrics.totalFiles}`, vscode.TreeItemCollapsibleState.None, 'metric'));
        items.push(new InsightItem(`🔄 Avg Complexity: ${metrics.avgComplexity}`, vscode.TreeItemCollapsibleState.None, 'metric'));
        items.push(new InsightItem(`⏱️ Tech Debt: ${metrics.techDebt}`, vscode.TreeItemCollapsibleState.None, 'metric'));
        return items;
    }
    getSuggestionItems() {
        if (!this.insights?.suggestions?.length) {
            return [new InsightItem('No suggestions available', vscode.TreeItemCollapsibleState.None, 'info')];
        }
        return this.insights.suggestions.map((suggestion) => {
            const priorityIcon = suggestion.priority === 'high' ? '🔴' : suggestion.priority === 'medium' ? '🟡' : '🟢';
            const typeIcon = suggestion.type === 'refactor' ? '🔧' : suggestion.type === 'optimize' ? '⚡' : '🧪';
            const item = new InsightItem(`${priorityIcon} ${typeIcon} ${suggestion.description}`, vscode.TreeItemCollapsibleState.None, 'suggestion');
            item.tooltip = `${suggestion.type} - ${suggestion.priority} priority`;
            return item;
        });
    }
}
exports.ProjectInsightsProvider = ProjectInsightsProvider;
class InsightItem extends vscode.TreeItem {
    constructor(label, collapsibleState, insightType) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.insightType = insightType;
        this.contextValue = insightType;
    }
}
//# sourceMappingURL=ProjectInsightsProvider.js.map