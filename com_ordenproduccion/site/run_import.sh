#!/bin/bash

# Historical Data Import Script
# This script runs the command-line import without Joomla framework

echo "=== Historical Data Import - Command Line ==="
echo "Starting import process at: $(date)"
echo ""

# Change to the script directory
cd "$(dirname "$0")"

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed or not in PATH"
    exit 1
fi

# Check if the import script exists
if [ ! -f "import_cli.php" ]; then
    echo "❌ import_cli.php not found"
    exit 1
fi

# Run the import script
echo "Running import script..."
php import_cli.php

# Check exit status
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Import completed successfully at: $(date)"
else
    echo ""
    echo "❌ Import failed at: $(date)"
    exit 1
fi
