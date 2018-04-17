<?php

namespace Tidy;

/** Format JavaScript. */
class JsPrinter extends Printer
{
    /**
     * Format (pretty print) some JS.
     *
     * @throws Exception
     *
     * @param string $source
     *
     * @return string
     */
    public function format($source)
    {
        $indent = $this->options['indent'];

        // Locate the bin file.
        $binFile = realpath($this->projectDir . '/node_modules/.bin/js-beautify');
        if (!$binFile) {
            throw new \Exception('js-beautify not found');
        }

        // Locate the config file.
        $configFile = realpath($this->projectDir . '/jsbeautifyrc');
        if (!$configFile) {
            throw new \Exception('js-beautify config file not found');
        }

        // Remove extra left space to force tidy to start in column 0.
        $source = preg_replace('/^[ \\t]+/m', '', $source);

        // Create a temporary file to format.
        $tempFile = tempnam('/tmp', 'js_source');
        file_put_contents($tempFile, $source);

        // Reformat file using js-beautify.
        $cmd = $binFile . ' -r --config ' . escapeshellarg($configFile) . ' %s';
        $this->runCommand($cmd, $tempFile);
        $pretty = file_get_contents($tempFile);

        // Wrap comments if asked to do so.
        if ($this->options['wrapComments']) {
            $pretty = $this->wrapComments($pretty);
        }
        $pretty = $this->trimExtraSpace($pretty);
        $pretty = $this->fixIndent($pretty, $indent);
        return $pretty;
    }
}
