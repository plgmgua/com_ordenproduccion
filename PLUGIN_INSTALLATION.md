# Markdown Renderer Plugin - Installation Guide

## 📦 Overview

The **Markdown Renderer Plugin** allows you to embed and display markdown files (like `ASISTENCIA.md`) directly in Joomla articles. This is perfect for displaying documentation, guides, and help content.

## 🚀 Installation Steps

### Step 1: Create Installation Package

Create a zip file of the plugin:

```bash
cd /path/to/com_ordenproduccion-1
zip -r plg_content_markdownrenderer.zip plg_content_markdownrenderer/
```

### Step 2: Install via Joomla Admin

1. Log in to Joomla Admin
2. Navigate to **Extensions → Manage → Install**
3. Click **"Upload Package File"**
4. Select the `plg_content_markdownrenderer.zip` file
5. Click **"Upload & Install"**

### Step 3: Enable the Plugin

1. Navigate to **Extensions → Plugins**
2. Search for **"Markdown Renderer"** or **"Renderizador Markdown"**
3. Click the plugin name to open it
4. Click **"Save"** (plugin should already be enabled after installation)

### Step 4: Configure Plugin Settings (Optional)

In the plugin configuration:

- **Enable Caching**: ✅ Yes (recommended)
- **Cache TTL**: `3600` seconds (1 hour)
- **Add Default Styles**: ✅ Yes (recommended)
- **Markdown Files Directory**: `components/com_ordenproduccion/`

## 📝 Usage

### Basic Syntax

In any Joomla article, use this syntax to render a markdown file:

```
{markdown:filename.md}
```

### Example

**Article content:**

```
Welcome to our documentation!

Here is the Asistencia manual:

{markdown:ASISTENCIA.md}

For more information, contact support.
```

**Result:** The entire `ASISTENCIA.md` file will be converted to HTML and displayed in the article with proper styling.

### Supported Markdown Features

- ✅ Headers (# ## ###)
- ✅ Code blocks (```code```) and inline code (`code`)
- ✅ **Bold**, *italic*, ~~strikethrough~~
- ✅ [Links](url) and ![Images](url)
- ✅ Lists (ordered and unordered)
- ✅ > Blockquotes
- ✅ Horizontal rules (---)
- ✅ Tables
- ✅ Emojis 😊
- ✅ Line breaks and paragraphs

## 📁 File Locations

By default, the plugin looks for markdown files in:

```
/joomla_root/components/com_ordenproduccion/
```

You can change this path in the plugin settings.

### Current Markdown Files

- `ASISTENCIA.md` - Attendance and timesheet documentation

Place any additional markdown files in the same directory.

## 🔒 Security Features

- ✅ Path validation to prevent directory traversal
- ✅ Only files within configured directory are accessible
- ✅ XSS prevention through proper HTML escaping
- ✅ Cache is scoped to plugin to prevent conflicts

## ⚙️ Configuration Options

### Enable Caching

Caches rendered markdown to improve performance. Disable only for testing.

### Cache TTL (Time to Live)

How long cached content remains valid:
- `3600` = 1 hour (default)
- `86400` = 24 hours
- `60` = 1 minute (for testing)

### Add Default Styles

Automatically adds CSS for proper markdown rendering. Disable if you prefer custom styling.

### Markdown Files Directory

Relative path to where markdown files are stored:
- Default: `components/com_ordenproduccion/`
- Must be relative to Joomla root
- Trailing slash optional

## 🐛 Troubleshooting

### Plugin Not Working

1. Check if plugin is enabled in **Extensions → Plugins**
2. Verify markdown file exists in configured directory
3. Check file path is correct (no spaces, proper case)
4. Clear Joomla cache: **System → Clear Cache**

### File Not Found Error

1. Verify file exists: `JPATH_ROOT/components/com_ordenproduccion/filename.md`
2. Check file permissions (should be readable)
3. Verify path prefix setting in plugin config
4. Check for typos in filename

### Styling Issues

1. Enable "Add Default Styles" in plugin settings
2. Clear browser cache
3. Check for CSS conflicts with your template

### Caching Issues

1. Clear Joomla cache: **System → Clear Cache**
2. Temporarily disable caching to test
3. Decrease Cache TTL for faster updates

## 📊 Performance

- Initial rendering: ~50-100ms per file
- Cached rendering: ~1-5ms per file
- Cache benefits: 20-50x faster on subsequent loads

## 🔄 Updates

To update the plugin:

1. Download new version
2. Uninstall old version (preserves configuration)
3. Install new version
4. Enable and configure if needed

## 🆘 Support

For issues or questions:

1. Check this installation guide
2. Review plugin README.md
3. Check Joomla error logs: **System → System Information → Logs**
4. Contact Grimpsa support

---

**Version:** 1.0.0  
**Compatibility:** Joomla 5.0+  
**Last Updated:** 2025-01-31

