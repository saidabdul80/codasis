# Augment AI Replica - Setup Guide

This guide will help you set up the complete Augment AI Replica system with VSCode extension and Laravel backend.

## Prerequisites

- **Node.js** (v16 or higher)
- **PHP** (8.2 or higher)
- **Composer**
- **VSCode**
- **Git**

## AI Model API Keys

You'll need API keys for at least one of these AI models:

### DeepSeek R1 (Recommended)
- Sign up at: https://platform.deepseek.com/
- Get API key from dashboard
- Cost: Very affordable, excellent performance

### OpenAI GPT-4
- Sign up at: https://platform.openai.com/
- Get API key from dashboard
- Cost: Higher but excellent quality

### Anthropic Claude
- Sign up at: https://console.anthropic.com/
- Get API key from dashboard
- Cost: Moderate, excellent for code analysis

### Google Gemini
- Sign up at: https://makersuite.google.com/
- Get API key from dashboard
- Cost: Competitive pricing

## Backend Setup

### 1. Navigate to Laravel Backend
```bash
cd laravel-backend
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment Variables
Edit `.env` file and add your AI model API keys:

```env
# Required: At least one AI model API key
DEEPSEEK_API_KEY=your_deepseek_api_key_here
OPENAI_API_KEY=your_openai_api_key_here
ANTHROPIC_API_KEY=your_anthropic_api_key_here
GOOGLE_API_KEY=your_google_api_key_here

# Database (SQLite is default, no setup needed)
DB_CONNECTION=sqlite

# App Configuration
APP_NAME="Augment AI Replica"
APP_URL=http://localhost:8000
```

### 5. Run Migrations
```bash
php artisan migrate
```

### 6. Start the Server
```bash
php artisan serve
```

The backend will be available at `http://localhost:8000`

## VSCode Extension Setup

### 1. Navigate to Extension Directory
```bash
cd vscode-extension
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Compile TypeScript
```bash
npm run compile
```

### 4. Install Extension in VSCode

#### Option A: Development Mode
1. Open VSCode
2. Press `F5` to open Extension Development Host
3. The extension will be loaded automatically

#### Option B: Package and Install
```bash
npm install -g vsce
vsce package
code --install-extension augment-ai-replica-0.0.1.vsix
```

### 5. Configure Extension
1. Open VSCode Settings (`Ctrl+,`)
2. Search for "Augment AI"
3. Configure:
   - **API URL**: `http://localhost:8000/api`
   - **API Key**: (Leave empty for now, will be set after user registration)
   - **Preferred Model**: `deepseek-r1` (or your preferred model)

## First Time Usage

### 1. Register a User Account
Use any HTTP client (Postman, curl, etc.) to register:

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Your Name",
    "email": "your.email@example.com",
    "password": "your_secure_password",
    "password_confirmation": "your_secure_password"
  }'
```

### 2. Get Your API Token
The registration response will include a `token`. Copy this token.

### 3. Configure VSCode Extension
1. Open VSCode Settings
2. Find "Augment AI: Api Key"
3. Paste your token

## Testing the Setup

### 1. Test Backend Health
```bash
curl http://localhost:8000/api/health
```

### 2. Test AI Models
```bash
curl -X GET http://localhost:8000/api/public/models
```

### 3. Test VSCode Extension
1. Open any code file in VSCode
2. Select some code
3. Right-click and choose "Explain Selected Code"
4. Or use `Ctrl+Shift+A` to ask a question

## Available Features

### VSCode Extension Commands
- `Ctrl+Shift+A`: Ask AI Question
- `Ctrl+Shift+C`: Open AI Chat
- Right-click menu:
  - Explain Selected Code
  - Refactor Code
  - Generate Tests
  - Analyze Code

### Backend API Endpoints
- `POST /api/ai/ask`: General AI questions
- `POST /api/ai/analyze`: Code analysis
- `POST /api/ai/explain`: Code explanation
- `POST /api/ai/generate-tests`: Test generation
- `POST /api/ai/refactor`: Code refactoring
- `POST /api/ai/completions`: Code completions
- `POST /api/chat/`: Chat conversations

## Troubleshooting

### Common Issues

#### 1. "Model not available" Error
- Check that you've added the correct API key in `.env`
- Verify the API key is valid by testing directly with the AI provider
- Check the logs: `tail -f laravel-backend/storage/logs/laravel.log`

#### 2. VSCode Extension Not Working
- Ensure the backend is running (`php artisan serve`)
- Check the API URL in VSCode settings
- Verify your authentication token is correct
- Check VSCode Developer Console (`Help > Toggle Developer Tools`)

#### 3. Authentication Issues
- Make sure you've registered a user account
- Verify the token is correctly set in VSCode settings
- Check token format (should start with a number and contain letters/numbers)

#### 4. CORS Issues
- The backend is configured for `localhost:3000` by default
- If using different ports, update `CORS_ALLOWED_ORIGINS` in `.env`

### Performance Optimization

#### 1. Enable Response Caching
```env
CACHE_AI_RESPONSES=true
CACHE_TTL_SECONDS=3600
```

#### 2. Use Redis for Better Caching
```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

#### 3. Adjust Rate Limits
```env
RATE_LIMIT_PER_MINUTE=100
RATE_LIMIT_PER_HOUR=2000
```

## Security Considerations

1. **Never commit API keys** to version control
2. **Use environment variables** for all sensitive configuration
3. **Enable HTTPS** in production
4. **Implement proper rate limiting** to prevent abuse
5. **Regularly rotate API keys**

## Next Steps

1. **Customize the AI prompts** in `AIModelService.php`
2. **Add more AI models** by extending the service
3. **Implement user preferences** for model selection
4. **Add conversation history** in the VSCode extension
5. **Create custom commands** for specific use cases

## Support

For issues and questions:
1. Check the logs in `laravel-backend/storage/logs/`
2. Enable debug mode: `APP_DEBUG=true` in `.env`
3. Test API endpoints directly with curl or Postman
4. Check VSCode Developer Console for extension errors

## License

MIT License - Feel free to modify and distribute as needed.
