#!/bin/bash

# 🏗️ DIGIBASE ENTERPRISE UPGRADE - INSTALLATION SCRIPT
# This script installs all battle-tested packages for Digibase

echo "🚀 Starting Digibase Enterprise Upgrade..."
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📦 PHASE 1: Installing Packages${NC}"
echo "-----------------------------------"

# Install Core Packages
echo "Installing Spatie Media Library..."
composer require spatie/laravel-medialibrary:"^11.0"

echo "Installing Filament Media Library Plugin..."
composer require filament/spatie-laravel-media-library-plugin:"^3.2"

echo "Installing Log Viewer..."
composer require opcodesio/log-viewer:"^3.0"

echo "Installing Filament Backup Plugin..."
composer require shuvroroy/filament-spatie-laravel-backup:"^2.0"

echo ""
echo -e "${YELLOW}📝 PHASE 2: Publishing Configurations${NC}"
echo "---------------------------------------"

# Publish Configurations
echo "Publishing Media Library migrations..."
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"

echo "Publishing Media Library config..."
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"

echo "Publishing Backup config..."
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"

echo ""
echo -e "${YELLOW}🗄️  PHASE 3: Running Migrations${NC}"
echo "-----------------------------------"

# Run Migrations
echo "Running database migrations..."
php artisan migrate

echo ""
echo -e "${YELLOW}🔗 PHASE 4: Creating Storage Links${NC}"
echo "------------------------------------"

# Create Storage Links
echo "Creating storage symlink..."
php artisan storage:link

echo ""
echo -e "${YELLOW}🧹 PHASE 5: Clearing Cache${NC}"
echo "----------------------------"

# Clear Cache
echo "Clearing configuration cache..."
php artisan config:clear

echo "Clearing application cache..."
php artisan cache:clear

echo "Clearing route cache..."
php artisan route:clear

echo "Clearing view cache..."
php artisan view:clear

echo ""
echo -e "${GREEN}✅ Installation Complete!${NC}"
echo "=========================="
echo ""
echo "📋 Next Steps:"
echo "1. Update code files as per ENTERPRISE_UPGRADE_IMPLEMENTATION.md"
echo "2. Test file uploads in DataExplorer"
echo "3. Access Log Viewer at /admin/log-viewer"
echo "4. Configure backups at /admin/backups"
echo "5. Follow ENTERPRISE_CLEANUP_GUIDE.md to remove old code"
echo ""
echo "📚 Documentation:"
echo "- ENTERPRISE_UPGRADE_GUIDE.md - Overview"
echo "- ENTERPRISE_UPGRADE_IMPLEMENTATION.md - Step-by-step code changes"
echo "- ENTERPRISE_CLEANUP_GUIDE.md - Cleanup instructions"
echo ""
echo -e "${GREEN}🎉 Happy coding!${NC}"
