<?php
namespace Tidy;

use csstidy;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;

/** Format CSS. */
class CssPrinter extends Printer
{
    /**
     * Format (pretty print) some CSS.
     *
     * @param string $source
     *
     * @return string
     */
    public function format($source)
    {
        $indent = $this->options['indent'];
        $pretty = '';

        // Save comments that occur outside of CSS.
        $parts = preg_split('~(/\*.*?\*/)~s', $source, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        // Create formatter.
        $format = \Sabberworm\CSS\OutputFormat::createPretty()->indentWithSpaces(self::DEFAULT_INDENT);

        // Process each part.
        foreach ($parts as $part) {
            // Save comments and process each part.
            if (substr($part, 0, 2) == '/*') {
                $pretty .= $part . "\n";
            } else {
                $this->checkBlock($part);
                $part = $this->applyCerdic($part);
                $part = $this->applySabberworm($format, $part);
                $part = $this->breakLongLines($part);
                $pretty .= trim($part) . "\n\n";
            }
        }

        // Apply extra fixes.
        $pretty = $this->trimExtraSpace($pretty);
        $pretty = $this->fixIndent($pretty, $indent);
        return $pretty;
    }

    /**
     * Apply the CSS tidy options using the Cerdic parser.
     *
     * @param string $source
     *
     * @return string
     */
    protected function applyCerdic($source)
    {
        $tidy = new csstidy();
        $tidy->set_cfg('case_properties', 1);

        // Should we sort the selectors?
        if ($this->options['sortSelectors']) {
            $tidy->set_cfg('sort_selectors', true);
        }

        // Should we sort the properties?
        if ($this->options['sortProperties']) {
            $tidy->set_cfg('sort_properties', true);
        }
        $tidy->parse($source);
        $pretty = $tidy->print->plain();
        return $pretty;
    }

    /**
     * Apply the CSS tidy options using the Sabberworm parser.
     *
     * @param OutputFormat $format
     * @param string $source
     *
     * @return string
     */
    protected function applySabberworm(OutputFormat $format, $source)
    {
        $parser = new Parser($source);
        $document = $parser->parse();
        $pretty = $document->render($format);
        return $pretty;
    }

    /**
     * Break apart long lines.
     *
     * @param string $source
     * @return string
     */
    protected function breakLongLines($source)
    {
        $lines = explode("\n", $source);
        $newLines = [];
        foreach ($lines as $line) {
            if (strlen($line) > 80) {
                preg_match('/^\s*/', $line, $match);
                $indent = ",\n" . $match[0] . '    ';
                $parts = preg_split('/,\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                $line = implode($indent, $parts);
            }
            $newLines[] = $line;
        }
        $newSource = implode("\n", $newLines);
        return $newSource;
    }

    /**
     * Check that comments did not occur inside a CSS block.
     *
     * @throws Exception
     *
     * @param string $block
     */
    protected function checkBlock($block)
    {
        $counts = ['{' => 0, '}' => 0];
        if (preg_match_all('/[{}]/', $block, $matches)) {
            foreach ($matches[0] as $match) {
                $counts[$match]++;
            }
        }
        if ($counts['{'] != $counts['}']) {
            throw new \Exception('Comment found inside CSS block');
        }
    }
}
