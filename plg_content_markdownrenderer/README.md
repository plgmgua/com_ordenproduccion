# Markdown Renderer Plugin

Joomla Content Plugin to render markdown files inside articles.

## Installation

This plugin is distributed as part of the `com_ordenproduccion` component package.

### Manual Installation

1. Zip the `plg_content_markdownrenderer` folder
2. Go to Joomla Admin → Extensions → Manage → Install
3. Upload the zip file
4. Go to Extensions → Plugins
5. Search for "Markdown Renderer"
6. Enable the plugin

## Usage

In any Joomla article, use the following syntax to render a markdown file:

```
{markdown:filename}
```

For example:

```
{markdown:ASISTENCIA.md}
```

The plugin will automatically:
- Read the markdown file from the configured directory
- Convert it to HTML
- Render it in the article with styling
- Cache the result for performance

## Configuration

### Plugin Settings

- **Enable Caching**: Toggle caching for better performance
- **Cache TTL**: How long to cache rendered content (default: 1 hour)
- **Add Default Styles**: Automatically add CSS for markdown rendering
- **Markdown Files Directory**: Where markdown files are stored (default: `components/com_ordenproduccion/`)

### Supported Markdown Features

- Headers (# H1, ## H2, etc.)
- Code blocks (```code```) and inline code (`code`)
- Bold (**text**), italic (*text*), strikethrough (~~text~~)
- Links [text](url) and images ![alt](url)
- Lists (ordered and unordered)
- Blockquotes (> text)
- Horizontal rules (---)
- Tables (| col1 | col2 |)
- Emojis (common shortcuts)
- Line breaks and paragraphs

## Security

- File paths are validated to prevent directory traversal
- Only files within the configured directory are accessible
- All output is properly escaped for XSS prevention

## Example

### Article Content

```
Welcome to our documentation system.

{markdown:ASISTENCIA.md}

For more information, contact support.
```

### Result

The markdown file is automatically converted to HTML and displayed in the article with proper styling.

## Support

For issues or feature requests, contact Grimpsa support.

---

**Version:** 1.0.0  
**License:** GNU General Public License version 2 or later  
**Author:** Grimpsa

