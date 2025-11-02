<?php
/**
 * @package     Grimpsa\Plugin\Content\Markdownrenderer
 * @subpackage  plg_content_markdownrenderer
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

/**
 * Markdown Renderer content plugin
 *
 * Renders markdown files embedded in Joomla articles using {markdown:filename.md} syntax
 *
 * @since  1.5.0
 */
class PlgContentMarkdownrenderer extends JPlugin
{
    /**
     * Render markdown files
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   &$article The article object
     * @param   object   &$params  The article params
     * @param   integer  $page     Optional page number
     *
     * @return  void
     *
     * @since   1.5.0
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Don't run if content is empty
        if (empty($article->text)) {
            return;
        }

        // Find all {markdown:filename} tags
        $pattern = '/\{markdown:([^\}]+)\}/i';
        
        if (!preg_match_all($pattern, $article->text, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $filename = trim($match[1]);

            if (empty($filename)) {
                continue;
            }

            try {
                $html = $this->renderMarkdownFile($filename);
                $article->text = str_replace($fullMatch, $html, $article->text);
            } catch (Exception $e) {
                // Log error but don't break the page
                JFactory::getApplication()->enqueueMessage(
                    'Markdown rendering error: ' . $e->getMessage(),
                    'warning'
                );
            }
        }
    }

    /**
     * Render markdown file to HTML
     *
     * @param   string  $filename  Markdown filename
     *
     * @return  string  Rendered HTML
     *
     * @throws  Exception
     * @since   1.5.0
     */
    protected function renderMarkdownFile($filename)
    {
        // Ensure file has .md extension
        if (substr($filename, -3) !== '.md') {
            $filename .= '.md';
        }

        // Build file path
        $pathPrefix = $this->params->get('path_prefix', 'components/com_ordenproduccion/');
        $filePath = JPATH_ROOT . '/' . $pathPrefix . $filename;

        // Security: Check if file exists
        if (!file_exists($filePath)) {
            throw new Exception('Markdown file not found: ' . $filename);
        }

        // Security: Ensure file is within allowed directory (prevent directory traversal)
        $realPath = realpath($filePath);
        $allowedPath = realpath(JPATH_ROOT . '/' . $pathPrefix);
        
        if (!$allowedPath || strpos($realPath, $allowedPath) !== 0) {
            throw new Exception('Invalid file path');
        }

        // Check cache
        $enableCache = $this->params->get('enable_cache', 1);
        
        if ($enableCache) {
            $cacheKey = 'markdown_' . md5($realPath . filemtime($realPath));
            $cache = JFactory::getCache('plg_content_markdownrenderer', '');
            $cache->setCaching(true);
            $cache->setLifeTime((int) $this->params->get('cache_ttl', 3600));
            
            $cachedHtml = $cache->get($cacheKey);
            
            if ($cachedHtml !== false) {
                return $cachedHtml;
            }
        }

        // Read and parse markdown
        $markdown = file_get_contents($filePath);
        $html = $this->parseMarkdown($markdown);

        // Add wrapper and styles if enabled
        if ($this->params->get('add_styles', 1)) {
            $html = $this->wrapWithStyles($html);
        }

        // Cache the result
        if ($enableCache) {
            $cache->store($html, $cacheKey);
        }

        return $html;
    }

    /**
     * Parse markdown text to HTML
     *
     * @param   string  $markdown  Markdown text
     *
     * @return  string  HTML output
     *
     * @since   1.5.0
     */
    protected function parseMarkdown($markdown)
    {
        if (empty($markdown)) {
            return '';
        }

        // Convert to HTML
        $html = $this->convertHeaders($markdown);
        $html = $this->convertCodeBlocks($html);
        $html = $this->convertInlineCode($html);
        $html = $this->convertBold($html);
        $html = $this->convertItalic($html);
        $html = $this->convertStrikethrough($html);
        $html = $this->convertImages($html);  // Must process images before links!
        $html = $this->convertLinks($html);
        $html = $this->convertLists($html);
        $html = $this->convertBlockquotes($html);
        $html = $this->convertHorizontalRules($html);
        $html = $this->convertEmojis($html);
        $html = $this->convertParagraphs($html);

        return $html;
    }

    /**
     * Convert headers (# ## ### #### ##### ######)
     */
    protected function convertHeaders($text)
    {
        for ($i = 6; $i >= 1; $i--) {
            $pattern = '/^' . str_repeat('#', $i) . '\s+(.+)$/m';
            $text = preg_replace($pattern, '<h' . $i . '>$1</h' . $i . '>', $text);
        }
        return $text;
    }

    /**
     * Convert code blocks (```code```)
     */
    protected function convertCodeBlocks($text)
    {
        $pattern = '/```(\w+)?\n(.*?)```/s';
        return preg_replace_callback($pattern, function($matches) {
            $language = isset($matches[1]) ? $matches[1] : '';
            $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<pre><code' . ($language ? ' class="language-' . $language . '"' : '') . '>' . $code . '</code></pre>';
        }, $text);
    }

    /**
     * Convert inline code (`code`)
     */
    protected function convertInlineCode($text)
    {
        $pattern = '/`([^`]+)`/';
        return preg_replace_callback($pattern, function($matches) {
            return '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
        }, $text);
    }

    /**
     * Convert bold text (**text**)
     */
    protected function convertBold($text)
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        return $text;
    }

    /**
     * Convert italic text (*text*)
     */
    protected function convertItalic($text)
    {
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
        return $text;
    }

    /**
     * Convert strikethrough text (~~text~~)
     */
    protected function convertStrikethrough($text)
    {
        return preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
    }

    /**
     * Convert links [text](url)
     */
    protected function convertLinks($text)
    {
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        return preg_replace_callback($pattern, function($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '">' . $text . '</a>';
        }, $text);
    }

    /**
     * Convert images ![alt](url)
     */
    protected function convertImages($text)
    {
        $pattern = '/!\[([^\]]*)\]\(([^)]+)\)/';
        return preg_replace_callback($pattern, function($matches) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<img src="' . $src . '" alt="' . $alt . '" />';
        }, $text);
    }

    /**
     * Convert lists (- item or * item or 1. item)
     */
    protected function convertLists($text)
    {
        // Ordered lists
        $pattern = '/^(\d+\.\s+.+(?:\n(?!\d+\.\s+|$).+)*)/m';
        $text = preg_replace_callback($pattern, function($matches) {
            $lines = preg_split('/\n(?=\d+\.\s+)/', $matches[0]);
            $items = '';
            foreach ($lines as $line) {
                $line = preg_replace('/^\d+\.\s+/', '', trim($line));
                $items .= '<li>' . $line . '</li>';
            }
            return '<ol>' . $items . '</ol>';
        }, $text);

        // Unordered lists
        $pattern = '/^([-*]\s+.+(?:\n(?![-*]\s+|$).+)*)/m';
        $text = preg_replace_callback($pattern, function($matches) {
            $lines = preg_split('/\n(?=[-*]\s+)/', $matches[0]);
            $items = '';
            foreach ($lines as $line) {
                $line = preg_replace('/^[-*]\s+/', '', trim($line));
                $items .= '<li>' . $line . '</li>';
            }
            return '<ul>' . $items . '</ul>';
        }, $text);

        return $text;
    }

    /**
     * Convert blockquotes (> text)
     */
    protected function convertBlockquotes($text)
    {
        $pattern = '/^>\s+(.+)$/m';
        return preg_replace_callback($pattern, function($matches) {
            return '<blockquote>' . $matches[1] . '</blockquote>';
        }, $text);
    }

    /**
     * Convert horizontal rules (--- or *** or ___)
     */
    protected function convertHorizontalRules($text)
    {
        $pattern = '/^---$/m';
        $text = preg_replace($pattern, '<hr>', $text);
        $pattern = '/^\*\*\*$/m';
        $text = preg_replace($pattern, '<hr>', $text);
        $pattern = '/^___$/m';
        $text = preg_replace($pattern, '<hr>', $text);
        return $text;
    }

    /**
     * Convert emojis
     */
    protected function convertEmojis($text)
    {
        $emojis = [
            ':)' => '😊', ':(' => '😢', ':D' => '😃',
            ':-)' => '😊', ':-(' => '😢', ':-D' => '😃',
            '<3' => '❤️', '</3' => '💔',
        ];
        foreach ($emojis as $shortcut => $emoji) {
            $text = str_replace($shortcut, $emoji, $text);
        }
        return $text;
    }

    /**
     * Convert paragraphs (wrap in <p> tags)
     */
    protected function convertParagraphs($text)
    {
        $lines = preg_split('/\n\n/', $text);
        $paragraphs = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (preg_match('/^<[huo][^>]*>/', $line) || 
                preg_match('/^<pre>/', $line) || 
                preg_match('/^<blockquote>/', $line) ||
                preg_match('/^<img/', $line)) {
                $paragraphs[] = $line;
            } else {
                $paragraphs[] = '<p>' . $line . '</p>';
            }
        }
        
        return implode("\n", $paragraphs);
    }

    /**
     * Wrap HTML content with markdown styles
     */
    protected function wrapWithStyles($html)
    {
        $style = '
        <style>
            .markdown-content {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.3;
                color: #333;
                max-width: 100%;
            }
            .markdown-content h1, .markdown-content h2, .markdown-content h3,
            .markdown-content h4, .markdown-content h5, .markdown-content h6 {
                margin-top: 1em;
                margin-bottom: 0.5em;
                font-weight: bold;
                line-height: 1.2;
            }
            .markdown-content h1 { font-size: 2em; border-bottom: 1px solid #eaecef; padding-bottom: .3em; }
            .markdown-content h2 { font-size: 1.5em; border-bottom: 1px solid #eaecef; padding-bottom: .3em; }
            .markdown-content h3 { font-size: 1.25em; }
            .markdown-content h4 { font-size: 1em; }
            .markdown-content h5 { font-size: 0.875em; }
            .markdown-content h6 { font-size: 0.85em; color: #6a737d; }
            .markdown-content p { margin: 0 0 0.5em; line-height: 1.3; }
            .markdown-content ul, .markdown-content ol {
                margin: 0 0 0.5em;
                padding-left: 2em;
                line-height: 1.3;
            }
            .markdown-content li { margin: 0.15em 0; line-height: 1.3; }
            .markdown-content blockquote {
                margin: 0;
                padding: 0 1em;
                color: #6a737d;
                border-left: 0.25em solid #dfe2e5;
            }
            .markdown-content code {
                background-color: #f6f8fa;
                border-radius: 3px;
                font-size: 85%;
                margin: 0;
                padding: 0.2em 0.4em;
            }
            .markdown-content pre {
                background-color: #f6f8fa;
                border-radius: 6px;
                font-size: 85%;
                line-height: 1.45;
                overflow: auto;
                padding: 16px;
                word-wrap: normal;
            }
            .markdown-content pre code {
                background-color: transparent;
                border: 0;
                display: inline;
                line-height: inherit;
                margin: 0;
                max-width: auto;
                overflow: visible;
                padding: 0;
                word-wrap: normal;
            }
            .markdown-content table {
                border-collapse: collapse;
                border-spacing: 0;
                width: 100%;
                margin-bottom: 1em;
            }
            .markdown-content table th,
            .markdown-content table td {
                border: 1px solid #dfe2e5;
                padding: 6px 13px;
            }
            .markdown-content table th {
                background-color: #f6f8fa;
                font-weight: bold;
            }
            .markdown-content table tr:nth-child(2n) {
                background-color: #f6f8fa;
            }
            .markdown-content hr {
                height: 0.25em;
                padding: 0;
                margin: 1.5em 0;
                background-color: #e1e4e8;
                border: 0;
            }
            .markdown-content a {
                color: #0366d6;
                text-decoration: none;
            }
            .markdown-content a:hover {
                text-decoration: underline;
            }
            .markdown-content img {
                max-width: 100%;
                height: auto;
            }
            .markdown-content strong { font-weight: bold; }
            .markdown-content em { font-style: italic; }
            .markdown-content del { text-decoration: line-through; }
        </style>
        <div class="markdown-content">' . $html . '</div>';

        return $style;
    }
}
