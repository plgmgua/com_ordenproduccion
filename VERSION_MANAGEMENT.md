# Version Management Guide

**Component:** com_ordenproduccion  
**Current Version:** 2.0.2-STABLE  
**Last Updated:** January 2025  

---

## Overview

This document describes the version management system for the Production Orders Management System (`com_ordenproduccion`). The system uses semantic versioning and automated version management tools.

## Version Format

We follow [Semantic Versioning](https://semver.org/) (SemVer) format:

```
MAJOR.MINOR.PATCH[-STAGE]
```

### Version Components

- **MAJOR**: Breaking changes that are not backward compatible
- **MINOR**: New features that are backward compatible
- **PATCH**: Bug fixes that are backward compatible
- **STAGE**: Optional pre-release stage (ALPHA, BETA, RC1, etc.)

### Examples

- `1.0.0` - Initial stable release
- `1.0.1` - Bug fix release
- `1.1.0` - New feature release
- `2.0.0` - Breaking changes release
- `1.0.0-ALPHA` - Alpha testing stage
- `1.0.0-BETA` - Beta testing stage
- `1.0.0-RC1` - Release candidate 1

## Version Management Script

The `version-manager.sh` script automates version updates, commits, and releases.

### Usage

```bash
./version-manager.sh [COMMAND] [OPTIONS]
```

### Commands

#### Patch Version (Bug Fixes)
```bash
./version-manager.sh patch -m "Fix webhook validation bug" -p -t
```
- Increments patch version (1.0.0 → 1.0.1)
- Use for bug fixes and minor improvements

#### Minor Version (New Features)
```bash
./version-manager.sh minor -m "Add new dashboard features" -p -t
```
- Increments minor version (1.0.0 → 1.1.0)
- Use for new features that are backward compatible

#### Major Version (Breaking Changes)
```bash
./version-manager.sh major -m "Refactor API endpoints" -p -t
```
- Increments major version (1.0.0 → 2.0.0)
- Use for breaking changes or major refactoring

#### Set Specific Version
```bash
./version-manager.sh set -v 1.2.3 -m "Release version 1.2.3" -p -t
```
- Sets a specific version number
- Use for hotfixes or special releases

#### Show Current Version
```bash
./version-manager.sh show
```
- Displays the current version

#### Create Release
```bash
./version-manager.sh release
```
- Creates a release with the current version
- Generates release notes and tags

### Options

- `-m, --message`: Commit message (required for patch/minor/major)
- `-v, --version`: Version number (required for set command)
- `-p, --push`: Push to remote repository
- `-t, --tag`: Create git tag

## Version Files

The version management system updates the following files:

### 1. VERSION
```
1.0.0
```
- Root version file
- Single source of truth for current version

### 2. com_ordenproduccion.xml
```xml
<version>1.0.0</version>
```
- Component manifest file
- Used by Joomla for component identification

### 3. CHANGELOG.md
```markdown
## [1.0.1] - 2025-01-27

### Fixed
- Fix webhook validation bug

## [Unreleased]
```
- Detailed changelog of all changes
- Automatically updated with each version bump

### 4. Component Files
- PHP files with version references
- Automatically updated during version bump

## Workflow

### 1. Development
- Work on features or bug fixes
- Test thoroughly
- Update documentation if needed

### 2. Version Update
```bash
# For bug fixes
./version-manager.sh patch -m "Fix webhook validation bug" -p -t

# For new features
./version-manager.sh minor -m "Add new dashboard features" -p -t

# For breaking changes
./version-manager.sh major -m "Refactor API endpoints" -p -t
```

### 3. Release Process
```bash
# Create release
./version-manager.sh release
```

### 4. Post-Release
- Update deployment documentation
- Notify stakeholders
- Monitor for issues

## Version History

| Version | Date | Type | Description |
|---------|------|------|-------------|
| 1.0.0 | 2025-01-27 | Initial | Complete Production Orders Management System |

## Best Practices

### 1. Version Naming
- Use descriptive commit messages
- Include issue numbers when applicable
- Be specific about what changed

### 2. Release Frequency
- **Patch**: As needed for critical bug fixes
- **Minor**: Monthly for new features
- **Major**: When breaking changes are necessary

### 3. Testing
- Always test before version bump
- Run the test suite
- Verify installation process

### 4. Documentation
- Update documentation with each release
- Include migration notes for major versions
- Update API documentation if needed

## Emergency Procedures

### Hotfix Process
```bash
# Create hotfix branch
git checkout -b hotfix/1.0.1

# Make critical fix
# ... make changes ...

# Commit and version bump
./version-manager.sh patch -m "Critical security fix" -p -t

# Merge to main
git checkout main
git merge hotfix/1.0.1
git push origin main
```

### Rollback Process
```bash
# Revert to previous version
git checkout v1.0.0

# Create rollback branch
git checkout -b rollback/1.0.0

# Update version
./version-manager.sh set -v 1.0.0 -m "Rollback to stable version" -p -t
```

## Integration with CI/CD

### GitHub Actions
```yaml
name: Version Management
on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Create Release
        run: |
          ./version-manager.sh release
```

### Automated Testing
```bash
# Run tests before version bump
php tests/run-tests.php

# Validate component
php com_ordenproduccion/validate.php
```

## Troubleshooting

### Common Issues

#### Version Format Error
```
Error: Invalid version format. Use MAJOR.MINOR.PATCH[-STAGE]
```
**Solution**: Ensure version follows semantic versioning format

#### Git Repository Error
```
Error: Not in a git repository
```
**Solution**: Run the script from the project root directory

#### Missing Commit Message
```
Error: Commit message is required
```
**Solution**: Use `-m` or `--message` option

### Manual Version Update

If the script fails, you can manually update versions:

```bash
# Update VERSION file
echo "1.0.1" > VERSION

# Update manifest
sed -i 's/<version>.*<\/version>/<version>1.0.1<\/version>/' com_ordenproduccion/com_ordenproduccion.xml

# Commit changes
git add .
git commit -m "chore: Bump version to 1.0.1"
git tag v1.0.1
git push origin main --tags
```

## Support

For version management issues:

1. Check this documentation
2. Review the version-manager.sh script
3. Check git status and logs
4. Contact the development team

---

**© 2025 Grimpsa. All rights reserved.**
