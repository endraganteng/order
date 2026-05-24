<?php

namespace App\Helpers;

class MarkdownHelper
{
    /**
     * Convert simple markdown to safe HTML.
     * Handles: ***bold italic***, **bold**, *italic*, bullet lists, newlines.
     */
    public static function toHtml(string $text): string
    {
        $s = e($text);

        // ***bold italic***
        $s = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $s);
        // **bold**
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        // *italic*
        $s = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $s);
        // bullet points: lines starting with - or •
        $s = preg_replace('/^[\-•]\s+(.+)/m', '<li>$1</li>', $s);
        $s = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $s);
        // cleanup nested ul
        $s = str_replace('</ul><ul>', '', $s);
        // newlines (but not inside ul)
        $s = preg_replace('/(?<!<\/li>)\n/', '<br>', $s);

        return $s;
    }
}
