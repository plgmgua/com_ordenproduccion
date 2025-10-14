#!/bin/bash

# Setup Google API Library for Google Drive Import Script
# This script installs the required Google API client library

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "=========================================="
echo "  Google API Library Setup Script"
echo "=========================================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Composer is not installed. Please install Composer first.${NC}"
    echo "Visit: https://getcomposer.org/download/"
    exit 1
fi

echo -e "${BLUE}Installing Google API Client Library...${NC}"
composer require google/apiclient

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Google API Client Library installed successfully${NC}"
else
    echo -e "${RED}❌ Failed to install Google API Client Library${NC}"
    exit 1
fi

# Check if the credentials file exists
if [ ! -f "helpers/leernuevacotizacion-7b11714cae3f.json" ]; then
    echo -e "${YELLOW}⚠️ Warning: Google service account credentials file not found${NC}"
    echo "Please ensure the file 'leernuevacotizacion-7b11714cae3f.json' is in the helpers folder"
    echo ""
fi

echo ""
echo "=========================================="
echo "  Setup Complete"
echo "=========================================="
echo ""
echo -e "${GREEN}✅ Google API Client Library is ready${NC}"
echo ""
echo "Next steps:"
echo "1. Ensure the Google service account credentials file is in helpers/"
echo "2. Run the import script: php google_drive_import.php"
echo ""
echo "The script will:"
echo "- Download Google Drive PDF files"
echo "- Organize them by year/month folders"
echo "- Rename files with COT prefix"
echo "- Update database with local paths"
echo "- Create detailed logs"
echo ""
