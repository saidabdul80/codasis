#!/bin/bash

# Augment AI Replica - Package and Deploy Script
# This script packages the VSCode extension and prepares the system for deployment

set -e

echo "🚀 Augment AI Replica - Package and Deploy"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "README.md" ] || [ ! -d "vscode-extension" ] || [ ! -d "laravel-backend" ]; then
    print_error "Please run this script from the project root directory"
    exit 1
fi

print_status "Checking prerequisites..."

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP first."
    exit 1
fi

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

print_success "Prerequisites check passed"

# Setup Laravel Backend
print_status "Setting up Laravel backend..."

cd laravel-backend

# Install dependencies if not already installed
if [ ! -d "vendor" ]; then
    print_status "Installing Laravel dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Setup environment if .env doesn't exist
if [ ! -f ".env" ]; then
    print_status "Creating environment file..."
    cp .env.example .env
    php artisan key:generate
    print_warning "Please configure your AI model API keys in laravel-backend/.env"
fi

# Run migrations
print_status "Running database migrations..."
php artisan migrate --force

# Clear and cache config
print_status "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_success "Laravel backend setup complete"

cd ..

# Setup VSCode Extension
print_status "Setting up VSCode extension..."

cd vscode-extension

# Install dependencies if not already installed
if [ ! -d "node_modules" ]; then
    print_status "Installing VSCode extension dependencies..."
    npm install
fi

# Compile TypeScript
print_status "Compiling TypeScript..."
npm run compile

# Install vsce if not available
if ! command -v vsce &> /dev/null; then
    print_status "Installing vsce (VSCode Extension Manager)..."
    npm install -g vsce
fi

# Package the extension
print_status "Packaging VSCode extension..."
vsce package

# Find the generated .vsix file
VSIX_FILE=$(ls *.vsix 2>/dev/null | head -n 1)

if [ -n "$VSIX_FILE" ]; then
    print_success "VSCode extension packaged: $VSIX_FILE"
    
    # Move to project root for easy access
    mv "$VSIX_FILE" "../$VSIX_FILE"
    print_status "Extension package moved to project root: $VSIX_FILE"
else
    print_error "Failed to package VSCode extension"
    exit 1
fi

cd ..

# Test AI Integration
print_status "Testing AI model integration..."

if [ -f "test-ai-integration.php" ]; then
    cd laravel-backend
    php ../test-ai-integration.php
    cd ..
else
    print_warning "AI integration test script not found"
fi

# Create deployment package
print_status "Creating deployment package..."

PACKAGE_NAME="augment-ai-replica-$(date +%Y%m%d-%H%M%S)"
mkdir -p "dist/$PACKAGE_NAME"

# Copy necessary files
cp -r laravel-backend "dist/$PACKAGE_NAME/"
cp -r vscode-extension "dist/$PACKAGE_NAME/"
cp *.md "dist/$PACKAGE_NAME/" 2>/dev/null || true
cp *.vsix "dist/$PACKAGE_NAME/" 2>/dev/null || true
cp test-ai-integration.php "dist/$PACKAGE_NAME/" 2>/dev/null || true

# Remove development files from Laravel
rm -rf "dist/$PACKAGE_NAME/laravel-backend/node_modules" 2>/dev/null || true
rm -rf "dist/$PACKAGE_NAME/laravel-backend/.git" 2>/dev/null || true
rm -rf "dist/$PACKAGE_NAME/laravel-backend/storage/logs/*" 2>/dev/null || true

# Remove development files from VSCode extension
rm -rf "dist/$PACKAGE_NAME/vscode-extension/node_modules" 2>/dev/null || true
rm -rf "dist/$PACKAGE_NAME/vscode-extension/src" 2>/dev/null || true
rm -rf "dist/$PACKAGE_NAME/vscode-extension/.git" 2>/dev/null || true

# Create archive
cd dist
tar -czf "$PACKAGE_NAME.tar.gz" "$PACKAGE_NAME"
cd ..

print_success "Deployment package created: dist/$PACKAGE_NAME.tar.gz"

# Final instructions
echo ""
echo "🎉 Package and Deploy Complete!"
echo "==============================="
echo ""
echo "📦 Files created:"
echo "  - VSCode Extension: $(ls *.vsix 2>/dev/null | head -n 1)"
echo "  - Deployment Package: dist/$PACKAGE_NAME.tar.gz"
echo ""
echo "🚀 Next steps:"
echo "  1. Configure AI API keys in laravel-backend/.env"
echo "  2. Start Laravel backend: cd laravel-backend && php artisan serve"
echo "  3. Install VSCode extension: code --install-extension $(ls *.vsix 2>/dev/null | head -n 1)"
echo "  4. Register a user and configure the extension"
echo ""
echo "📚 Documentation:"
echo "  - Setup Guide: setup.md"
echo "  - Demo Guide: demo.md"
echo "  - Test Script: test-ai-integration.php"
echo ""
echo "🔧 Troubleshooting:"
echo "  - Check logs: tail -f laravel-backend/storage/logs/laravel.log"
echo "  - Test API: curl http://localhost:8000/api/health"
echo "  - Test AI models: php test-ai-integration.php"
echo ""

print_success "All done! Your Augment AI Replica is ready to use! 🎉"
