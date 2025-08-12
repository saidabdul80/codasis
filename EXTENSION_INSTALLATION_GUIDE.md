# 🚀 VSCode Extension Installation Guide

## 📦 **Extension Package Created**

Your Augment AI Replica extension has been successfully packaged as:
**`codasis-0.0.1.vsix`** (492.17 KB)

## 🔧 **Installation Methods**

### Method 1: Install via VSCode UI (Recommended)

1. **Open VSCode**
2. **Open Command Palette**: `Ctrl+Shift+P` (Windows/Linux) or `Cmd+Shift+P` (Mac)
3. **Type**: `Extensions: Install from VSIX...`
4. **Select the file**: Navigate to `vscode-extension/codasis-0.0.1.vsix`
5. **Click Install**
6. **Reload VSCode** when prompted

### Method 2: Install via Command Line

```bash
# Navigate to the extension directory
cd vscode-extension

# Install the extension
code --install-extension codasis-0.0.1.vsix
```

### Method 3: Manual Installation

1. **Copy the VSIX file** to your VSCode extensions directory:
   - **Windows**: `%USERPROFILE%\.vscode\extensions\`
   - **macOS**: `~/.vscode/extensions/`
   - **Linux**: `~/.vscode/extensions/`

2. **Extract the VSIX file** (it's a ZIP file) to the extensions directory
3. **Restart VSCode**

## ⚙️ **Configuration Setup**

After installation, configure the extension:

### 1. Open VSCode Settings
- `Ctrl+,` (Windows/Linux) or `Cmd+,` (Mac)
- Or: `File > Preferences > Settings`

### 2. Search for "Augment AI"

### 3. Configure Required Settings
```json
{
  "augment-ai.apiUrl": "http://localhost:8000/api",
  "augment-ai.apiKey": "your-api-token-here",
  "augment-ai.preferredModel": "deepseek-r1",
  "augment-ai.autoComplete": true
}
```

## 🎯 **Verify Installation**

### 1. Check Extension is Active
- Open Command Palette (`Ctrl+Shift+P`)
- Type "Augment AI" - you should see the commands

### 2. Test Basic Functionality
- Press `Ctrl+Shift+A` to ask a question
- Press `Ctrl+Shift+C` to open chat
- Press `Ctrl+Shift+I` to index workspace

### 3. Check Activity Bar
- Look for the Augment AI icon in the left sidebar
- Click it to open the chat panel

## 🚨 **Troubleshooting**

### Issue: "Extension manifest not found"

**Solution 1: Check File Path**
- Ensure you're selecting the correct `.vsix` file
- The file should be in `vscode-extension/codasis-0.0.1.vsix`

**Solution 2: Re-download/Re-package**
```bash
cd vscode-extension
npm run compile
npx vsce package
```

**Solution 3: Clear VSCode Cache**
1. Close VSCode completely
2. Delete VSCode workspace cache:
   - **Windows**: `%APPDATA%\Code\User\workspaceStorage\`
   - **macOS**: `~/Library/Application Support/Code/User/workspaceStorage/`
   - **Linux**: `~/.config/Code/User/workspaceStorage/`
3. Restart VSCode and try again

### Issue: "Extension not loading"

**Check Developer Console:**
1. `Help > Toggle Developer Tools`
2. Look for errors in the Console tab
3. Check if there are any red error messages

**Common Fixes:**
- Ensure Laravel backend is running on `http://localhost:8000`
- Check API token is correctly configured
- Verify network connectivity

### Issue: Commands not appearing

**Solution:**
1. Open Command Palette (`Ctrl+Shift+P`)
2. Type: `Developer: Reload Window`
3. Try the commands again

## 🎮 **Available Commands**

Once installed, you'll have access to:

| Command | Shortcut | Description |
|---------|----------|-------------|
| Ask AI Question | `Ctrl+Shift+A` | Ask AI about your code with intelligent context |
| Open AI Chat | `Ctrl+Shift+C` | Open interactive chat panel |
| Index Workspace | `Ctrl+Shift+I` | Build intelligent codebase understanding |
| Smart Code Analysis | `Ctrl+Shift+S` | Analyze selected code with context |
| Explain Code | Right-click menu | Explain selected code |
| Generate Tests | Right-click menu | Generate tests for selected code |
| Refactor Code | Right-click menu | Refactor selected code |

## 🔗 **Backend Requirements**

Make sure your Laravel backend is running:

```bash
cd laravel-backend
php artisan serve
```

The backend should be accessible at `http://localhost:8000`

## 🎉 **Success Indicators**

You'll know the extension is working when:

1. ✅ **Commands appear** in Command Palette
2. ✅ **Chat panel opens** with `Ctrl+Shift+C`
3. ✅ **Workspace indexing works** with `Ctrl+Shift+I`
4. ✅ **AI responses** include context information
5. ✅ **No errors** in Developer Console

## 📞 **Need Help?**

If you're still having issues:

1. **Check the Developer Console** for specific error messages
2. **Verify backend is running** and accessible
3. **Test API endpoints** directly in browser/Postman
4. **Check VSCode version** (requires 1.74.0+)
5. **Try creating a new workspace** to test

The extension is now ready to provide you with intelligent, context-aware AI assistance! 🚀
