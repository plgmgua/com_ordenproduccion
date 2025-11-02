# Changelog

All notable changes to the Markdown Renderer plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.4] - 2025-11-01

### Fixed
- Italic conversion now only matches pure text (letters/numbers/spaces) to avoid breaking URLs and paths
- Underscores in URLs, file paths, and other non-text contexts are now preserved

## [1.6.3] - 2025-11-01

### Fixed
- Underscores in rendered HTML tags are now properly preserved
- Italic conversion now excludes HTML tags containing angle brackets

## [1.6.2] - 2025-11-01

### Fixed
- Image URLs with underscores no longer get converted to italic tags
- Italic conversion now happens after images and links to protect URLs

## [1.6.1] - 2025-11-01

### Fixed
- Images are no longer wrapped in paragraph tags, preventing display issues

## [1.6.0] - 2025-11-01

### Fixed
- Image markdown syntax now renders correctly by processing images before links

## [1.5.0] - 2025-11-01

### Changed
- Reduced paragraph spacing and line height for better readability
- Removed redundant line break conversion

### Fixed
- Plugin refactored to single-file architecture to fix Joomla class loading issues

## [1.0.0] - 2025-01-31

### Added
- Initial release
- Markdown to HTML conversion
- Support for headers (H1-H6)
- Code blocks (fenced and inline)
- Text formatting (bold, italic, strikethrough)
- Links and images
- Lists (ordered and unordered)
- Blockquotes
- Horizontal rules
- Tables
- Emoji shortcuts
- Caching system for performance
- Configurable directory for markdown files
- Optional CSS styling
- Security features (path validation, XSS prevention)
- Multi-language support (English, Spanish)

