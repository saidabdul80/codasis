# Augment AI Replica - Demo Guide

This guide demonstrates the key features of your Augment AI Replica system.

## 🚀 Quick Start Demo

### 1. Start the Backend
```bash
cd laravel-backend
php artisan serve
```

### 2. Test AI Models
```bash
php ../test-ai-integration.php
```

### 3. Register a User
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Demo User",
    "email": "demo@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

Save the returned `token` for VSCode configuration.

## 🎯 Feature Demonstrations

### 1. Code Analysis via API

Test the code analysis endpoint:

```bash
curl -X POST http://localhost:8000/api/ai/analyze \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "function fibonacci(n) {\n  if (n <= 1) return n;\n  return fibonacci(n - 1) + fibonacci(n - 2);\n}",
    "language": "javascript"
  }'
```

### 2. Code Explanation

```bash
curl -X POST http://localhost:8000/api/ai/explain \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "const users = await User.where(\"active\", true).with(\"posts\").get();",
    "language": "javascript"
  }'
```

### 3. Test Generation

```bash
curl -X POST http://localhost:8000/api/ai/generate-tests \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "function add(a, b) {\n  return a + b;\n}",
    "language": "javascript"
  }'
```

### 4. Code Refactoring

```bash
curl -X POST http://localhost:8000/api/ai/refactor \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "var x = 1; var y = 2; var z = x + y; console.log(z);",
    "language": "javascript"
  }'
```

### 5. Chat Conversation

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "message": "How can I optimize a React component for better performance?"
  }'
```

## 🔧 VSCode Extension Demo

### 1. Install Extension
1. Open VSCode
2. Press `Ctrl+Shift+P`
3. Type "Extensions: Install from VSIX"
4. Navigate to `vscode-extension` folder
5. Package with: `npx vsce package`
6. Install the generated `.vsix` file

### 2. Configure Extension
1. Open Settings (`Ctrl+,`)
2. Search "Augment AI"
3. Set:
   - **API URL**: `http://localhost:8000/api`
   - **API Key**: Your token from registration
   - **Preferred Model**: `deepseek-r1`

### 3. Test Extension Features

#### Ask AI Question
1. Press `Ctrl+Shift+A`
2. Type: "How do I handle async/await errors in JavaScript?"
3. See AI response

#### Explain Code
1. Open a JavaScript file
2. Select some code:
   ```javascript
   const result = await fetch('/api/data')
     .then(response => response.json())
     .catch(error => console.error(error));
   ```
3. Right-click → "Explain Selected Code"

#### Generate Tests
1. Select a function:
   ```javascript
   function calculateTotal(items) {
     return items.reduce((sum, item) => sum + item.price, 0);
   }
   ```
2. Right-click → "Generate Tests"

#### Refactor Code
1. Select code that needs improvement:
   ```javascript
   var data = [];
   for (var i = 0; i < items.length; i++) {
     if (items[i].active == true) {
       data.push(items[i]);
     }
   }
   ```
2. Right-click → "Refactor Code"

#### Open Chat
1. Press `Ctrl+Shift+C`
2. Chat with AI about your code
3. Ask questions like:
   - "How can I improve this function?"
   - "What's the best way to handle errors here?"
   - "Can you suggest a better algorithm?"

## 🎨 Advanced Features Demo

### 1. Multi-Model Comparison

Test different models for the same task:

```bash
# Test with DeepSeek R1
curl -X POST http://localhost:8000/api/ai/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "prompt": "Explain the difference between let, const, and var in JavaScript",
    "model": "deepseek-r1"
  }'

# Test with GPT-4 (if configured)
curl -X POST http://localhost:8000/api/ai/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "prompt": "Explain the difference between let, const, and var in JavaScript",
    "model": "gpt-4"
  }'
```

### 2. Context-Aware Analysis

```bash
curl -X POST http://localhost:8000/api/ai/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "prompt": "How can I optimize this function?",
    "context": "File: utils.js\nLanguage: javascript\n\nfunction processData(data) {\n  let result = [];\n  for (let i = 0; i < data.length; i++) {\n    if (data[i].status === \"active\") {\n      result.push({\n        id: data[i].id,\n        name: data[i].name,\n        processed: true\n      });\n    }\n  }\n  return result;\n}"
  }'
```

### 3. Conversation Management

```bash
# Start a conversation
CONV_RESPONSE=$(curl -s -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{"message": "I need help with React hooks"}')

# Extract conversation ID
CONV_ID=$(echo $CONV_RESPONSE | jq -r '.conversationId')

# Continue the conversation
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d "{\"message\": \"Can you show me an example of useEffect?\", \"conversationId\": \"$CONV_ID\"}"
```

## 📊 Performance Testing

### 1. Response Time Test
```bash
time curl -X POST http://localhost:8000/api/ai/explain \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "console.log(\"Hello, World!\");",
    "language": "javascript"
  }'
```

### 2. Concurrent Requests Test
```bash
# Run multiple requests simultaneously
for i in {1..5}; do
  curl -X POST http://localhost:8000/api/ai/ask \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer YOUR_TOKEN_HERE" \
    -d '{"prompt": "What is JavaScript?"}' &
done
wait
```

## 🔍 Debugging and Monitoring

### 1. Check Logs
```bash
tail -f laravel-backend/storage/logs/laravel.log
```

### 2. Monitor API Health
```bash
curl http://localhost:8000/api/health
```

### 3. Check Available Models
```bash
curl http://localhost:8000/api/public/models
```

### 4. View User Statistics
```bash
curl -X GET http://localhost:8000/api/analytics/usage \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 🎯 Real-World Use Cases

### 1. Code Review Assistant
1. Paste code into VSCode
2. Select problematic sections
3. Use "Analyze Code" to get suggestions
4. Apply improvements

### 2. Learning Tool
1. Find unfamiliar code online
2. Paste into VSCode
3. Use "Explain Code" to understand
4. Ask follow-up questions in chat

### 3. Test-Driven Development
1. Write a function
2. Use "Generate Tests" to create test cases
3. Run tests and fix issues
4. Refactor with AI suggestions

### 4. Documentation Helper
1. Select a complex function
2. Ask AI to "Document this function"
3. Get JSDoc or similar documentation
4. Add to your codebase

## 🚀 Next Steps

After the demo, consider:

1. **Customize prompts** in `AIModelService.php`
2. **Add more languages** support
3. **Implement caching** for better performance
4. **Add user preferences** for model selection
5. **Create custom commands** for specific workflows
6. **Integrate with Git** for commit message generation
7. **Add code quality metrics** and suggestions

## 📈 Success Metrics

Your system is working well if:
- ✅ AI responses are relevant and helpful
- ✅ Response times are under 10 seconds
- ✅ VSCode extension integrates smoothly
- ✅ Multiple AI models are available
- ✅ Chat conversations maintain context
- ✅ Code analysis provides actionable insights

Enjoy your Augment AI Replica! 🎉
