# 🧪 Extension Testing Guide

## 🎯 **Quick Installation Test**

After installing the extension, follow these steps to verify everything works:

### 1. **Basic Installation Check**
```bash
# Run the diagnostic script
cd vscode-extension
node diagnostic.js
```
Should show: `🎉 All checks passed!`

### 2. **VSCode Command Test**
1. Open VSCode
2. Press `Ctrl+Shift+P`
3. Type "Augment AI"
4. You should see these commands:
   - ✅ `Augment AI: Ask AI Question`
   - ✅ `Augment AI: Open AI Chat`
   - ✅ `Augment AI: Index Workspace for AI Context`
   - ✅ `Augment AI: Smart Code Analysis`

### 3. **Backend Connection Test**
1. Make sure Laravel backend is running:
   ```bash
   cd laravel-backend
   php artisan serve
   ```
2. Test API endpoint in browser: `http://localhost:8000/api/health`
3. Should return: `{"status":"ok","timestamp":"..."}`

### 4. **Extension Configuration Test**
1. Open VSCode Settings (`Ctrl+,`)
2. Search for "augment-ai"
3. Configure:
   ```json
   {
     "augment-ai.apiUrl": "http://localhost:8000/api",
     "augment-ai.apiKey": "test-token",
     "augment-ai.preferredModel": "deepseek-r1"
   }
   ```

### 5. **Workspace Indexing Test**
1. Open a code project in VSCode
2. Press `Ctrl+Shift+I` (Index Workspace)
3. Should show progress notification
4. Should complete with success message

### 6. **AI Question Test**
1. Press `Ctrl+Shift+A` (Ask Question)
2. Type: "What is this project about?"
3. Should get AI response with project context

### 7. **Chat Interface Test**
1. Press `Ctrl+Shift+C` (Open Chat)
2. Chat panel should open in sidebar
3. Type a message and send
4. Should get AI response

### 8. **Smart Analysis Test**
1. Select some code in editor
2. Press `Ctrl+Shift+S` (Smart Analysis)
3. Should show analysis in output panel
4. Should include context information

## 🚨 **Common Issues & Solutions**

### Issue: Commands not appearing
**Solution:**
```bash
# Reload VSCode window
Ctrl+Shift+P → "Developer: Reload Window"
```

### Issue: "Extension manifest not found"
**Solutions:**
1. **Check file path**: Ensure you're selecting the correct `.vsix` file
2. **Re-package extension**:
   ```bash
   cd vscode-extension
   npm run compile
   npx vsce package
   ```
3. **Clear VSCode cache**: Close VSCode, delete workspace cache, restart

### Issue: API connection failed
**Solutions:**
1. **Check backend is running**: `http://localhost:8000/api/health`
2. **Verify API URL in settings**: Should be `http://localhost:8000/api`
3. **Check firewall/antivirus**: May be blocking localhost connections

### Issue: No AI responses
**Solutions:**
1. **Check API token**: Configure valid token in settings
2. **Check model availability**: Ensure DeepSeek R1 is configured in backend
3. **Check logs**: Open Developer Tools → Console for errors

### Issue: Context not working
**Solutions:**
1. **Index workspace first**: Press `Ctrl+Shift+I`
2. **Check workspace folder**: Ensure a folder is open in VSCode
3. **Wait for indexing**: Large projects may take time to index

## 📊 **Performance Benchmarks**

### Expected Performance:
- **Extension activation**: < 2 seconds
- **Workspace indexing**: 1-5 seconds per 100 files
- **AI response time**: 2-10 seconds depending on model
- **Context retrieval**: < 1 second

### Memory Usage:
- **Extension overhead**: ~10-20 MB
- **Indexed workspace**: ~1-5 MB per 1000 files
- **Chat history**: ~1 KB per message

## 🎮 **Feature Test Checklist**

### Core Features:
- [ ] Extension loads without errors
- [ ] Commands appear in Command Palette
- [ ] Settings are configurable
- [ ] Backend connection works

### AI Features:
- [ ] Ask Question works with context
- [ ] Chat interface is functional
- [ ] Smart analysis provides insights
- [ ] Code explanation works

### Context Features:
- [ ] Workspace indexing completes
- [ ] Current file context is detected
- [ ] Related files are found
- [ ] Dependencies are mapped

### Advanced Features:
- [ ] Multiple AI models work
- [ ] Context optimization works
- [ ] Error handling is graceful
- [ ] Performance is acceptable

## 🎉 **Success Criteria**

Your extension is working correctly if:

1. ✅ **All commands are available** and functional
2. ✅ **AI responses include context** information
3. ✅ **Workspace indexing** completes successfully
4. ✅ **Chat interface** is responsive and helpful
5. ✅ **No errors** in Developer Console
6. ✅ **Performance** is smooth and responsive

## 📞 **Getting Help**

If tests fail:

1. **Run diagnostic**: `node diagnostic.js`
2. **Check Developer Console**: `Help → Toggle Developer Tools`
3. **Verify backend logs**: Check Laravel logs for errors
4. **Test API directly**: Use Postman/curl to test endpoints
5. **Check VSCode version**: Requires 1.74.0 or higher

The extension should now provide intelligent, context-aware AI assistance! 🚀
