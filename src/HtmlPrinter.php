<?php

namespace Tidy;

use tidy;

/** Format HTML. */
class HtmlPrinter extends Printer
{
    /** @var array Saved JavaScripts. */
    protected $scripts = [];

    /**
     * Format (pretty print) some HTML.
     *
     * @param string $source
     *
     * @return string
     */
    public function format($source)
    {
        $indent = $this->options['indent'];
        $showHead = $this->options['showHead'];
        $sortAttributes = $this->options['sortAttributes'];

        // Specify configuration.
        $config = [
            'new-blocklevel-tags' => 'tal:block,header,section,footer,nav',
            'output-xhtml' => true,
            'show-body-only' => !$showHead,
            'sort-attributes' => $sortAttributes,
            'wrap' => 0
        ];

        // Parse HTML, if possible.
        $tidy = new tidy();
        $tidy->parseString($source, $config, 'utf8');
        $tidy->cleanRepair();
        $pretty = trim($tidy);

        // Save embedded JavaScript because they have HTML.
        $pretty = $this->saveJavaScript($pretty);

        // Put each tag on a new line.
        $pretty = str_replace('<', "\n" . '<', $pretty);
        $pretty = str_replace('>', '>' . "\n", $pretty);

        // Reformat the HTML.
        $pretty = $this->indentTags($pretty);
        $pretty = $this->replaceEmptyTags($pretty);
        $pretty = $this->replaceSingleQuotes($pretty);
        $pretty = $this->replaceSplitTags($pretty);
        $pretty = $this->wrapLines($pretty);
        $pretty = $this->fixBlankLines($pretty);
        $pretty = $this->fixPhpTal($pretty);

        // Restore JavaScript.
        $pretty = $this->restoreJavaScript($pretty);
        $pretty = $this->trimExtraSpace($pretty);
        $pretty = $this->fixIndent($pretty, $indent);
        return $pretty;
    }

    /**
     * Add blank lines after each block and remove other blank lines.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function fixBlankLines($pretty)
    {
        // Remove sequences of newlines.
        $pretty = preg_replace('~\\n+\\s*\\n+~', "\n", $pretty);

        // Add newlines after each close block.
        $pretty = preg_replace('~^</[^>]+>$~m', '\\0' . "\n", $pretty);
        return $pretty;
    }

    /**
     * Fix PHPTAL references.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function fixPhpTal($pretty)
    {
        if (preg_match_all('/\\${.*?}/s', $pretty, $matches)) {
            foreach ($matches[0] as $oldRef) {
                $newRef = preg_replace('/\\s+/', ' ', $oldRef);
                if ($oldRef != $newRef) {
                    $pretty = str_replace($oldRef, $newRef, $pretty);
                }
            }
        }
        return $pretty;
    }

    /**
     * Indent tags based on level of nesting.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function indentTags($pretty)
    {
        // Split all the lines and process each.
        $lines = preg_split("~\n+~", trim($pretty));
        $counts = [];
        $indent = 0;
        $prevIndent = 0;
        $pretty = '';
        foreach ($lines as $line) {
            $counts = $this->updateTagCount($counts, $line);
            $indent = array_sum($counts);
            if ($indent < $prevIndent) {
                $ws = str_repeat(' ', self::DEFAULT_INDENT * $indent);
            } else {
                $ws = str_repeat(' ', self::DEFAULT_INDENT * $prevIndent);
            }
            $line = $ws . $line;
            $pretty .= $line . "\n";
            $prevIndent = $indent;
        }
        return $pretty;
    }

    /**
     * Replace empty tags by removing spaces.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function replaceEmptyTags($pretty)
    {
        $pretty = preg_replace('~(<([\\w:.-]+)\\b[^>]*>)\\s+(</\\2>)~s', '\\1\\3', $pretty);
        return $pretty;
    }

    /**
     * Replace single quotes with double quotes for attributes.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function replaceSingleQuotes($pretty)
    {
        if (preg_match_all('@<.*?>@s', $pretty, $matches)) {
            foreach ($matches[0] as $tag) {
                if (preg_match_all('@\\w+=\'([^"]*?)\'@', $tag, $attrMatches)) {
                    $newTag = $tag;
                    foreach ($attrMatches[0] as $attr) {
                        $newAttr = str_replace('\'', '"', $attr);
                        $newTag = str_replace($attr, $newAttr, $newTag);
                    }
                    $pretty = str_replace($tag, $newTag, $pretty);
                }
            }
        }
        return $pretty;
    }

    /**
     * Replace short tags that are less than 80 chars with single-line
     * equivalent.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function replaceSplitTags($pretty)
    {
        if (preg_match_all('~(<([\\w:.-]+).*?>)(.*\\n.*\\n\\s*)(</\\2>)~', $pretty, $matches)) {
            foreach ($matches[0] as $i => $match) {
                if (strlen($match) < 80) {
                    $short = $matches[1][$i] . trim($matches[3][$i]) . $matches[4][$i];
                    $pretty = str_replace($match, $short, $pretty);
                }
            }
        }
        return $pretty;
    }

    /**
     * Restore JavaScript.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function restoreJavaScript($pretty)
    {
        foreach ($this->scripts as $replace) {
            list($oldScript, $newScript) = $replace;

            // Fix one-line scripts that have been broken into two lines.
            if (preg_match('~(<script[^>]*>)\\s*\\n\\s*(</script>)~i', $oldScript, $match)) {
                $oldScript = $match[1] . $match[2];
            }
            $pretty = str_replace($newScript, $oldScript, $pretty);
        }
        return $pretty;
    }

    /**
     * Save JavaScript.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function saveJavaScript($pretty)
    {
        if (preg_match_all('~<script[^>]*>.*?</script>~s', $pretty, $matches)) {
            $scripts = $matches[0];
            foreach ($scripts as $i => $oldScript) {
                $newScript = sprintf('<script tempid="js%08d"></script>', $i);
                $pretty = str_replace($oldScript, $newScript, $pretty);
                $this->scripts[] = [
                    $oldScript,
                    $newScript
                ];
            }
        }
        return $pretty;
    }

    /**
     * Update the tag count for the current line.
     *
     * @param array $counts
     * @param string $line
     *
     * @return array
     */
    protected function updateTagCount($counts, $line)
    {
        if (preg_match_all('~<([\\w:.-]+).*?>~', $line, $matches)) {
            foreach ($matches[1] as $i => $tag) {
                // Skip empty tags.
                if (preg_match('~/\\s*>~', $matches[0][$i])) {
                    continue;
                }
                $tag = strtolower($tag);
                $counts[$tag] = isset($counts[$tag]) ? $counts[$tag] + 1 : 1;
            }
        }
        if (preg_match_all('~</([\\w:.-]+)~', $line, $matches)) {
            foreach ($matches[1] as $tag) {
                $tag = strtolower($tag);
                $counts[$tag] = isset($counts[$tag]) ? $counts[$tag] - 1 : -1;
            }
        }
        return $counts;
    }

    /**
     * Wrap long tags and lines of text.
     *
     * @param string $pretty
     *
     * @return string
     */
    protected function wrapLines($pretty)
    {
        $lines = explode("\n", $pretty);
        $pretty = '';
        foreach ($lines as $line) {
            if (preg_match('~^(\\s*)(<[\\w:.-]+)([^>]{80,})>~', $line, $match)) {
                $oldTag = $match[0];
                $ws = $match[1];
                $tag = $match[2];
                $attrib = $match[3];
                $newAttrib = preg_replace('/\\s+([\\w:.-]+=)/', "\n" . $ws . str_repeat(' ', self::DEFAULT_INDENT) . '\\1', $attrib);
                $newTag = $ws . $tag . $newAttrib . '>';
                $line = str_replace($oldTag, $newTag, $line);
            } elseif (!preg_match('~[<>]~', $line)) {
                preg_match('~^\\s*~', $line, $match);
                $ws = $match[0];
                $line = wordwrap($line, 80 - strlen($ws), "\n" . $ws);
            }
            $pretty .= $line . "\n";
        }
        return $pretty;
    }
}
