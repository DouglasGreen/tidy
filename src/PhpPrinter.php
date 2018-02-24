<?php
namespace Tidy;

/** Format PHP. */
class PhpPrinter extends Printer
{
    /**
     * Format (pretty print) some PHP.
     *
     * @param string $source
     *
     * @return string
     */
    public function format($source)
    {
        $indent = $this->options['indent'];

        // Locate the bin files.
        $binPath = realpath($this->projectDir . '/vendor/bin');

        // Create a temporary file to format.
        $tempFile = tempnam('/tmp', 'php_source');
        file_put_contents($tempFile, $source);

        // Check the syntax with lint before tidying.
        $cmd = 'php -l %s';
        $this->runCommand($cmd, $tempFile);

        // Reformat file using php-cs-fixer.
        $cmd = $binPath . '/php-cs-fixer -q fix %s';
        $this->runCommand($cmd, $tempFile);

        // Reformat file using phpcbf.
        $cmd = $binPath . '/phpcbf -q --extensions=php %s';
        $this->runCommand($cmd, $tempFile);

        // Fix issues in output.
        $lines = file($tempFile);
        $pretty = implode('', $lines);

        // Wrap comments if asked to do so.
        if ($this->options['wrapComments']) {
            $pretty = $this->wrapComments($pretty);
        }
        $pretty = $this->fixCommentIndent($pretty, $indent);
        $pretty = $this->fixControlStructure($pretty);
        $pretty = $this->trimExtraSpace($pretty) . "\n";
        $pretty = $this->fixIndent($pretty, $indent);
        return $pretty;
    }

    /**
     * Fix comment indent.
     *
     * @param string $source
     *
     * @return string
     */
    protected function fixCommentIndent($source)
    {
        $lines = explode("\n", $source);
        $count = count($lines);
        for ($index = 0; $index < $count - 1; $index++) {
            if (preg_match('~^(\s*)(//.*)~', $lines[$index], $match)) {
                $indent1 = $match[1];
                $comment = $match[2];
                preg_match('~^(\s*)~', $lines[$index + 1], $match);
                $indent2 = $match[1];
                if ($indent1 != $indent2) {
                    $lines[$index] = $indent2 . $comment;
                }
            }
        }
        $source = implode("\n", $lines);
        return $source;
    }

    /**
     * Remove empty lines at the start and end of control structures.
     *
     * @param string $source
     *
     * @return string
     */
    protected function fixControlStructure($source)
    {
        $lines = explode("\n", $source);
        $count = count($lines);
        $newLines = [];
        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            if (!trim($line)) {
                // Skip empty line after start of control structure or case.
                $prevLine = $index > 0 ? $lines[$index - 1] : null;
                if (preg_match('/[{:]\s*$/', $prevLine)) {
                    continue;
                }

                // Skip empty line before end of control structure.
                $nextLine = $index < $count - 1 ? $lines[$index + 1] : null;
                if (preg_match('/^\s*}/', $nextLine)) {
                    continue;
                }
            }
            $newLines[] = $line;
        }
        $source = implode("\n", $newLines);
        return $source;
    }
}
