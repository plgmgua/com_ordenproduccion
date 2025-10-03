#!/bin/bash

# Version Management Script for com_ordenproduccion
# This script handles version updates, commits, and releases

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPONENT_NAME="com_ordenproduccion"
VERSION_FILE="VERSION"
CHANGELOG_FILE="CHANGELOG.md"
MANIFEST_FILE="com_ordenproduccion/com_ordenproduccion.xml"

# Current version
CURRENT_VERSION=$(cat $VERSION_FILE 2>/dev/null || echo "1.0.0")

# Function to display help
show_help() {
    echo -e "${BLUE}Version Manager for $COMPONENT_NAME${NC}"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  patch     Increment patch version (1.0.0 -> 1.0.1)"
    echo "  minor     Increment minor version (1.0.0 -> 1.1.0)"
    echo "  major     Increment major version (1.0.0 -> 2.0.0)"
    echo "  set       Set specific version (e.g., 1.2.3)"
    echo "  show      Show current version"
    echo "  release   Create a release with current version"
    echo "  help      Show this help message"
    echo ""
    echo "Options:"
    echo "  -m, --message    Commit message (required for patch/minor/major)"
    echo "  -v, --version    Version number (required for set command)"
    echo "  -p, --push       Push to remote repository"
    echo "  -t, --tag        Create git tag"
    echo ""
    echo "Examples:"
    echo "  $0 patch -m \"Fix webhook validation bug\" -p -t"
    echo "  $0 minor -m \"Add new dashboard features\" -p"
    echo "  $0 set -v 1.2.3 -m \"Release version 1.2.3\" -p -t"
}

# Function to validate version format
validate_version() {
    local version=$1
    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Z0-9]+)?$ ]]; then
        echo -e "${RED}Error: Invalid version format. Use MAJOR.MINOR.PATCH[-STAGE]${NC}"
        echo "Examples: 1.0.0, 1.0.1, 1.1.0, 2.0.0, 1.0.0-BETA"
        exit 1
    fi
}

# Function to increment version
increment_version() {
    local version=$1
    local type=$2
    
    IFS='.' read -r major minor patch <<< "$version"
    
    case $type in
        "major")
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        "minor")
            minor=$((minor + 1))
            patch=0
            ;;
        "patch")
            patch=$((patch + 1))
            ;;
        *)
            echo -e "${RED}Error: Invalid increment type. Use major, minor, or patch${NC}"
            exit 1
            ;;
    esac
    
    echo "$major.$minor.$patch"
}

# Function to update version in files
update_version_files() {
    local new_version=$1
    local commit_message=$2
    
    echo -e "${BLUE}Updating version to $new_version...${NC}"
    
    # Update VERSION file
    echo "$new_version" > $VERSION_FILE
    echo -e "${GREEN}✓ Updated VERSION file${NC}"
    
    # Update manifest file
    if [ -f "$MANIFEST_FILE" ]; then
        sed -i.bak "s/<version>.*<\/version>/<version>$new_version<\/version>/" $MANIFEST_FILE
        rm -f "${MANIFEST_FILE}.bak"
        echo -e "${GREEN}✓ Updated manifest file${NC}"
    fi
    
    # Update changelog
    update_changelog "$new_version" "$commit_message"
    
    # Update component files that reference version
    update_component_version "$new_version"
}

# Function to update changelog
update_changelog() {
    local version=$1
    local message=$2
    local date=$(date '+%Y-%m-%d')
    
    # Create changelog if it doesn't exist
    if [ ! -f "$CHANGELOG_FILE" ]; then
        cat > $CHANGELOG_FILE << EOF
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

EOF
    fi
    
    # Add new version entry
    local temp_file=$(mktemp)
    {
        echo "## [$version] - $date"
        echo ""
        echo "### Added"
        echo "- $message"
        echo ""
        echo "## [Unreleased]"
        echo ""
        cat $CHANGELOG_FILE | sed '/## \[Unreleased\]/,$d'
    } > $temp_file
    
    mv $temp_file $CHANGELOG_FILE
    echo -e "${GREEN}✓ Updated changelog${NC}"
}

# Function to update component version references
update_component_version() {
    local version=$1
    
    # Update version in component files
    find com_ordenproduccion -name "*.php" -type f -exec sed -i.bak "s/version.*=.*['\"].*['\"]/version = '$version'/g" {} \;
    find com_ordenproduccion -name "*.php" -type f -exec rm -f {}.bak \;
    
    echo -e "${GREEN}✓ Updated component version references${NC}"
}

# Function to commit changes
commit_changes() {
    local version=$1
    local message=$2
    
    echo -e "${BLUE}Committing changes...${NC}"
    
    git add .
    git commit -m "chore: Bump version to $version

$message

Version: $version"
    
    echo -e "${GREEN}✓ Committed changes${NC}"
}

# Function to create git tag
create_tag() {
    local version=$1
    
    echo -e "${BLUE}Creating git tag v$version...${NC}"
    
    git tag -a "v$version" -m "Release version $version"
    
    echo -e "${GREEN}✓ Created tag v$version${NC}"
}

# Function to push to remote
push_to_remote() {
    local version=$1
    local create_tag=$2
    
    echo -e "${BLUE}Pushing to remote repository...${NC}"
    
    git push origin main
    
    if [ "$create_tag" = "true" ]; then
        git push origin "v$version"
    fi
    
    echo -e "${GREEN}✓ Pushed to remote${NC}"
}

# Function to show current version
show_version() {
    echo -e "${BLUE}Current version: ${GREEN}$CURRENT_VERSION${NC}"
}

# Function to create release
create_release() {
    local version=$CURRENT_VERSION
    local message="Release version $version"
    
    echo -e "${BLUE}Creating release for version $version...${NC}"
    
    # Create release notes
    local release_notes=$(mktemp)
    {
        echo "# Release $version"
        echo ""
        echo "## Changes in this version:"
        echo ""
        # Extract changes from changelog
        sed -n "/## \[$version\]/,/## \[/p" $CHANGELOG_FILE | head -n -1 | tail -n +2
    } > $release_notes
    
    # Create tag
    create_tag "$version"
    
    # Push to remote
    push_to_remote "$version" "true"
    
    echo -e "${GREEN}✓ Release $version created successfully${NC}"
    echo -e "${YELLOW}Release notes saved to: $release_notes${NC}"
}

# Main script logic
main() {
    local command=$1
    shift
    
    local commit_message=""
    local new_version=""
    local push_to_remote=false
    local create_tag=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -m|--message)
                commit_message="$2"
                shift 2
                ;;
            -v|--version)
                new_version="$2"
                shift 2
                ;;
            -p|--push)
                push_to_remote=true
                shift
                ;;
            -t|--tag)
                create_tag=true
                shift
                ;;
            *)
                echo -e "${RED}Error: Unknown option $1${NC}"
                show_help
                exit 1
                ;;
        esac
    done
    
    case $command in
        "patch"|"minor"|"major")
            if [ -z "$commit_message" ]; then
                echo -e "${RED}Error: Commit message is required for $command command${NC}"
                echo "Use -m or --message option"
                exit 1
            fi
            
            new_version=$(increment_version "$CURRENT_VERSION" "$command")
            validate_version "$new_version"
            
            update_version_files "$new_version" "$commit_message"
            commit_changes "$new_version" "$commit_message"
            
            if [ "$create_tag" = "true" ]; then
                create_tag "$new_version"
            fi
            
            if [ "$push_to_remote" = "true" ]; then
                push_to_remote "$new_version" "$create_tag"
            fi
            
            echo -e "${GREEN}✓ Version updated from $CURRENT_VERSION to $new_version${NC}"
            ;;
            
        "set")
            if [ -z "$new_version" ]; then
                echo -e "${RED}Error: Version is required for set command${NC}"
                echo "Use -v or --version option"
                exit 1
            fi
            
            if [ -z "$commit_message" ]; then
                commit_message="Set version to $new_version"
            fi
            
            validate_version "$new_version"
            
            update_version_files "$new_version" "$commit_message"
            commit_changes "$new_version" "$commit_message"
            
            if [ "$create_tag" = "true" ]; then
                create_tag "$new_version"
            fi
            
            if [ "$push_to_remote" = "true" ]; then
                push_to_remote "$new_version" "$create_tag"
            fi
            
            echo -e "${GREEN}✓ Version set to $new_version${NC}"
            ;;
            
        "show")
            show_version
            ;;
            
        "release")
            create_release
            ;;
            
        "help"|"--help"|"-h")
            show_help
            ;;
            
        *)
            echo -e "${RED}Error: Unknown command '$command'${NC}"
            show_help
            exit 1
            ;;
    esac
}

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}Error: Not in a git repository${NC}"
    exit 1
fi

# Run main function
main "$@"
