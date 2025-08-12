#!/usr/bin/env node

/**
 * Diagnostic script for Augment AI VSCode Extension
 * Run this to check if everything is set up correctly
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 Augment AI Extension Diagnostic Tool\n');

// Check if package.json exists and is valid
function checkPackageJson() {
    console.log('📦 Checking package.json...');
    
    try {
        const packagePath = path.join(__dirname, 'package.json');
        if (!fs.existsSync(packagePath)) {
            console.log('❌ package.json not found');
            return false;
        }
        
        const packageContent = fs.readFileSync(packagePath, 'utf8');
        const packageJson = JSON.parse(packageContent);
        
        // Check required fields
        const requiredFields = ['name', 'version', 'publisher', 'engines', 'main', 'contributes'];
        const missingFields = requiredFields.filter(field => !packageJson[field]);
        
        if (missingFields.length > 0) {
            console.log(`❌ Missing required fields: ${missingFields.join(', ')}`);
            return false;
        }
        
        console.log('✅ package.json is valid');
        console.log(`   Name: ${packageJson.name}`);
        console.log(`   Version: ${packageJson.version}`);
        console.log(`   Publisher: ${packageJson.publisher}`);
        return true;
        
    } catch (error) {
        console.log(`❌ Error reading package.json: ${error.message}`);
        return false;
    }
}

// Check if compiled files exist
function checkCompiledFiles() {
    console.log('\n🔨 Checking compiled files...');
    
    const outDir = path.join(__dirname, 'out');
    if (!fs.existsSync(outDir)) {
        console.log('❌ out/ directory not found. Run: npm run compile');
        return false;
    }
    
    const mainFile = path.join(outDir, 'extension.js');
    if (!fs.existsSync(mainFile)) {
        console.log('❌ extension.js not found in out/. Run: npm run compile');
        return false;
    }
    
    console.log('✅ Compiled files exist');
    return true;
}

// Check if VSIX package exists
function checkVsixPackage() {
    console.log('\n📦 Checking VSIX package...');
    
    const vsixFiles = fs.readdirSync(__dirname).filter(file => file.endsWith('.vsix'));
    
    if (vsixFiles.length === 0) {
        console.log('❌ No VSIX package found. Run: npx vsce package');
        return false;
    }
    
    console.log(`✅ VSIX package found: ${vsixFiles[0]}`);
    
    // Check file size
    const vsixPath = path.join(__dirname, vsixFiles[0]);
    const stats = fs.statSync(vsixPath);
    const sizeKB = Math.round(stats.size / 1024);
    console.log(`   Size: ${sizeKB} KB`);
    
    if (sizeKB < 100) {
        console.log('⚠️  Package seems small, might be missing dependencies');
    }
    
    return true;
}

// Check dependencies
function checkDependencies() {
    console.log('\n📚 Checking dependencies...');
    
    const nodeModules = path.join(__dirname, 'node_modules');
    if (!fs.existsSync(nodeModules)) {
        console.log('❌ node_modules not found. Run: npm install');
        return false;
    }
    
    // Check for axios (main dependency)
    const axiosPath = path.join(nodeModules, 'axios');
    if (!fs.existsSync(axiosPath)) {
        console.log('❌ axios dependency not found. Run: npm install');
        return false;
    }
    
    console.log('✅ Dependencies installed');
    return true;
}

// Check TypeScript configuration
function checkTypeScriptConfig() {
    console.log('\n📝 Checking TypeScript configuration...');
    
    const tsconfigPath = path.join(__dirname, 'tsconfig.json');
    if (!fs.existsSync(tsconfigPath)) {
        console.log('❌ tsconfig.json not found');
        return false;
    }
    
    try {
        const tsconfigContent = fs.readFileSync(tsconfigPath, 'utf8');
        const tsconfig = JSON.parse(tsconfigContent);
        
        if (!tsconfig.compilerOptions || !tsconfig.compilerOptions.outDir) {
            console.log('❌ Invalid TypeScript configuration');
            return false;
        }
        
        console.log('✅ TypeScript configuration is valid');
        return true;
        
    } catch (error) {
        console.log(`❌ Error reading tsconfig.json: ${error.message}`);
        return false;
    }
}

// Main diagnostic function
function runDiagnostic() {
    const checks = [
        checkPackageJson,
        checkTypeScriptConfig,
        checkDependencies,
        checkCompiledFiles,
        checkVsixPackage
    ];
    
    const results = checks.map(check => check());
    const passed = results.filter(result => result).length;
    const total = results.length;
    
    console.log(`\n📊 Diagnostic Results: ${passed}/${total} checks passed`);
    
    if (passed === total) {
        console.log('\n🎉 All checks passed! Your extension should install correctly.');
        console.log('\n📋 Installation Instructions:');
        console.log('1. Open VSCode');
        console.log('2. Press Ctrl+Shift+P');
        console.log('3. Type "Extensions: Install from VSIX..."');
        console.log('4. Select the .vsix file in this directory');
        console.log('5. Reload VSCode when prompted');
    } else {
        console.log('\n⚠️  Some checks failed. Please fix the issues above and try again.');
        console.log('\n🔧 Quick fixes:');
        console.log('- Run: npm install');
        console.log('- Run: npm run compile');
        console.log('- Run: npx vsce package');
    }
}

// Run the diagnostic
runDiagnostic();
