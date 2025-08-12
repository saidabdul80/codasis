# Augment AI Replica

A VSCode plugin and Laravel backend system that provides AI-powered code assistance using DeepSeek R1 and other powerful language models.

## Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   VSCode        │    │   Laravel       │    │   AI Models     │
│   Extension     │◄──►│   Backend       │◄──►│   (DeepSeek R1) │
│                 │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Components

### 1. VSCode Extension (`vscode-extension/`)
- **Language**: TypeScript
- **Features**:
  - Code analysis and suggestions
  - Real-time AI assistance
  - Context-aware code completion
  - Codebase understanding
  - Chat interface for AI interactions

### 2. Laravel Backend (`laravel-backend/`)
- **Language**: PHP 8.2+
- **Features**:
  - RESTful API for AI interactions
  - User authentication and management
  - AI model integration and routing
  - Codebase analysis services
  - Response caching and optimization

### 3. AI Model Integration
- **Primary Model**: DeepSeek R1
- **Secondary Models**: GPT-4, Claude, Gemini
- **Features**:
  - Multi-model support
  - Intelligent model selection
  - Response aggregation
  - Performance optimization

## Project Structure

```
augment_replica/
├── vscode-extension/          # VSCode plugin source
│   ├── src/
│   ├── package.json
│   └── tsconfig.json
├── laravel-backend/           # Laravel API backend
│   ├── app/
│   ├── routes/
│   ├── config/
│   └── composer.json
├── shared/                    # Shared types and utilities
├── docs/                      # Documentation
└── docker/                    # Docker configuration
```

## Getting Started

1. **Backend Setup**:
   ```bash
   cd laravel-backend
   composer install
   php artisan serve
   ```

2. **Extension Development**:
   ```bash
   cd vscode-extension
   npm install
   npm run compile
   ```

3. **AI Model Configuration**:
   - Configure API keys in `.env`
   - Set up model endpoints
   - Configure rate limiting

## Features

- 🤖 **Multi-Model AI Integration**: Support for DeepSeek R1, GPT-4, Claude, and more
- 🔍 **Codebase Analysis**: Deep understanding of your entire codebase
- 💬 **Interactive Chat**: Natural language conversations about your code
- 🚀 **Real-time Suggestions**: Context-aware code completions and improvements
- 🔐 **Secure Authentication**: JWT-based authentication with role management
- 📊 **Analytics**: Usage tracking and performance metrics
- 🎯 **Smart Routing**: Intelligent model selection based on query type

## License

MIT License
