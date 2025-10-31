<?php
/**
 * @package     Grimpsa\Plugin\Content\Markdownrenderer
 * @subpackage  Markdown
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

namespace Grimpsa\Plugin\Content\Markdownrenderer\Markdown;

/**
 * Simple Markdown Parser
 *
 * Converts Markdown text to HTML
 *
 * @since  1.0.0
 */
class Parser
{
    /**
     * Parse markdown text to HTML
     *
     * @param   string  $markdown  Markdown text
     *
     * @return  string  HTML output
     *
     * @since   1.0.0
     */
    public function parse($markdown)
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
        $html = $this->convertLinks($html);
        $html = $this->convertImages($html);
        $html = $this->convertLists($html);
        $html = $this->convertBlockquotes($html);
        $html = $this->convertHorizontalRules($html);
        $html = $this->convertTables($html);
        $html = $this->convertLineBreaks($html);
        $html = $this->convertParagraphs($html);
        $html = $this->convertEmojis($html);

        return $html;
    }

    /**
     * Convert headers (# ## ### #### ##### ######)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertHeaders($text)
    {
        // H1 - H6
        for ($i = 6; $i >= 1; $i--) {
            $pattern = '/^' . str_repeat('#', $i) . '\s+(.+)$/m';
            $text = preg_replace($pattern, '<h' . $i . '>$1</h' . $i . '>', $text);
        }

        return $text;
    }

    /**
     * Convert code blocks (```code```)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertCodeBlocks($text)
    {
        // Fenced code blocks
        $pattern = '/```(\w+)?\n(.*?)```/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $language = isset($matches[1]) ? $matches[1] : '';
            $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<pre><code' . ($language ? ' class="language-' . $language . '"' : '') . '>' . $code . '</code></pre>';
        }, $text);
    }

    /**
     * Convert inline code (`code`)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
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
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertBold($text)
    {
        // Strong: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        return $text;
    }

    /**
     * Convert italic text (*text*)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertItalic($text)
    {
        // Emphasis: *text* or _text_
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
        
        return $text;
    }

    /**
     * Convert strikethrough text (~~text~~)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertStrikethrough($text)
    {
        return preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
    }

    /**
     * Convert links [text](url)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertLinks($text)
    {
        // Inline links: [text](url)
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        
        return preg_replace_callback($pattern, function($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '">' . $text . '</a>';
        }, $text);
    }

    /**
     * Convert images ![alt](url)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
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
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertLists($text)
    {
        // Ordered lists (1. item)
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

        // Unordered lists (- or *)
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
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
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
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
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
     * Convert tables (| col1 | col2 |)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertTables($text)
    {
        $pattern = '/^\|.+\|$/m';
        
        return preg_replace_callback($pattern, function($matches) {
            $row = trim($matches[0], '|');
            $cells = array_map('trim', explode('|', $row));
            
            $html = '<tr>';
            foreach ($cells as $cell) {
                // Check if it's a header row (next line would be |---|---|---)
                // For simplicity, treat first row as header
                if (strpos($cell, '---') !== false) {
                    return ''; // Skip separator row
                }
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
            
            return $html;
        }, $text);
    }

    /**
     * Convert emojis (unicode emojis)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertEmojis($text)
    {
        // Convert common markdown emoji syntax to unicode
        $emojis = [
            ':)' => '😊',
            ':(' => '😢',
            ':D' => '😃',
            ':-)' => '😊',
            ':-(' => '😢',
            ':-D' => '😃',
            '<3' => '❤️',
            '</3' => '💔',
        ];
        
        foreach ($emojis as $shortcut => $emoji) {
            $text = str_replace($shortcut, $emoji, $text);
        }
        
        return $text;
    }

    /**
     * Convert line breaks (two spaces + newline)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
     */
    protected function convertLineBreaks($text)
    {
        // Convert double newlines to paragraph breaks (handled by paragraphs)
        $text = preg_replace('/\n\n+/', "\n\n", $text);
        
        // Convert single newlines to <br>
        $text = preg_replace('/(?<=\S)\n(?=\S)/', '<br>', $text);
        
        return $text;
    }

    /**
     * Convert paragraphs (wrap in <p> tags)
     *
     * @param   string  $text  Markdown text
     *
     * @return  string  HTML
     *
     * @since   1.0.0
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
            
            // Don't wrap if already wrapped in another tag
            if (preg_match('/^<[huo][^>]*>/', $line) || 
                preg_match('/^<pre>/', $line) || 
                preg_match('/^<blockquote>/', $line) ||
                preg_match('/^<table>/', $line)) {
                $paragraphs[] = $line;
            } else {
                $paragraphs[] = '<p>' . $line . '</p>';
            }
        }
        
        return implode("\n", $paragraphs);
    }
}

