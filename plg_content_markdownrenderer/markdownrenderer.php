<?php
/**
 * @package     Grimpsa\Plugin\Content\Markdownrenderer
 * @subpackage  plg_content_markdownrenderer
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

// Require the parser class from media folder
$parserPath = JPATH_ROOT . '/media/plg_content_markdownrenderer/src/Markdown/Parser.php';
if (file_exists($parserPath)) {
    require_once $parserPath;
} else {
    // Fallback for development
    require_once __DIR__ . '/media/src/Markdown/Parser.php';
}

use Grimpsa\Plugin\Content\Markdownrenderer\Markdown\Parser;

/**
 * Markdown Renderer content plugin
 *
 * Renders markdown files embedded in Joomla articles using {markdown:filename.md} syntax
 *
 * @since  1.5.0
 */
class PlgContentMarkdownrenderer extends CMSPlugin
{
    /**
     * Application object
     *
     * @var    object
     * @since  1.5.0
     */
    protected $app;

    /**
     * Parser instance
     *
     * @var    Parser
     * @since  1.5.0
     */
    protected $parser;

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An optional associative array of configuration settings
     *
     * @since   1.5.0
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->parser = null;
    }

    /**
     * Initialize parser instance
     *
     * @return  void
     *
     * @since   1.5.0
     */
    protected function initParser()
    {
        if ($this->parser === null) {
            $this->parser = new Parser();
        }
    }

    /**
     * Plugin that renders markdown files
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
        // Don't run in admin or API
        if ($this->app->isClient('administrator') || $this->app->isClient('api')) {
            return;
        }

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
            } catch (\Exception $e) {
                // Log error but don't break the page
                Factory::getApplication()->enqueueMessage(
                    sprintf('Markdown rendering error: %s', $e->getMessage()),
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
     * @throws  \Exception
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
            throw new \Exception(sprintf('Markdown file not found: %s', $filename));
        }

        // Security: Ensure file is within allowed directory (prevent directory traversal)
        $realPath = realpath($filePath);
        $allowedPath = realpath(JPATH_ROOT . '/' . $pathPrefix);
        
        if (!$allowedPath || strpos($realPath, $allowedPath) !== 0) {
            throw new \Exception('Invalid file path');
        }

        // Check cache
        $enableCache = $this->params->get('enable_cache', 1);
        
        if ($enableCache) {
            $cacheKey = 'markdown_' . md5($realPath . filemtime($realPath));
            $cache = Factory::getCache('plg_content_markdownrenderer', '');
            $cache->setCaching(true);
            $cache->setLifeTime((int) $this->params->get('cache_ttl', 3600));
            
            $cachedHtml = $cache->get($cacheKey);
            
            if ($cachedHtml !== false) {
                return $cachedHtml;
            }
        }

        // Read and parse markdown
        $markdown = file_get_contents($filePath);
        
        // Initialize parser if needed
        $this->initParser();
        $html = $this->parser->parse($markdown);

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
     * Wrap HTML content with markdown styles
     *
     * @param   string  $html  Raw HTML content
     *
     * @return  string  HTML with styles
     *
     * @since   1.5.0
     */
    protected function wrapWithStyles($html)
    {
        $style = '
        <style>
            .markdown-content {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 100%;
            }
            .markdown-content h1, .markdown-content h2, .markdown-content h3,
            .markdown-content h4, .markdown-content h5, .markdown-content h6 {
                margin-top: 1.5em;
                margin-bottom: 0.5em;
                font-weight: bold;
                line-height: 1.25;
            }
            .markdown-content h1 { font-size: 2em; border-bottom: 1px solid #eaecef; padding-bottom: .3em; }
            .markdown-content h2 { font-size: 1.5em; border-bottom: 1px solid #eaecef; padding-bottom: .3em; }
            .markdown-content h3 { font-size: 1.25em; }
            .markdown-content h4 { font-size: 1em; }
            .markdown-content h5 { font-size: 0.875em; }
            .markdown-content h6 { font-size: 0.85em; color: #6a737d; }
            .markdown-content p { margin: 0 0 1em; }
            .markdown-content ul, .markdown-content ol {
                margin: 0 0 1em;
                padding-left: 2em;
            }
            .markdown-content li { margin: 0.25em 0; }
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
