<?php

namespace Tidy;

/** Base class for formatters. */
abstract class Printer
{
    /** @var int Default indent is four spaces */
    const DEFAULT_INDENT = 4;

    /** @var array Extensions to match when processing directories */
    protected $exts = [];

    /** @var array Operands */
    protected $operands = [];

    /** @var array Configuration ptions */
    protected $options = [];

    /** @var string Base directory of project. */
    protected $projectDir;

    /**
     * Add an extension to match when processing directories.
     *
     * @param string $ext
     */
    public function addExt($ext)
    {
        $this->exts[] = $ext;
    }

    /**
     * Add the operands from argv.
     *
     * @param array $argv
     */
    public function addOperands(array $argv)
    {
        // Exclude name of script.
        array_shift($argv);

        // Skip over options and get operands.
        foreach ($argv as $arg) {
            if (substr($arg, 0, 1) != '-') {
                $this->operands[] = $arg;
            }
        }

        // If no operands, assume stdin as input and stdout as output.
        if (!$this->operands) {
            $this->operands[] = 'php://stdin';
        }
    }

    /**
     * Fix the indent if not same as default.
     *
     * @param string $source
     * @param int $indent
     *
     * @return string
     */
    public function fixIndent($source, $indent)
    {
        if ($indent == self::DEFAULT_INDENT) {
            return $source;
        }
        $lines = explode("\n", $source);
        $newLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^( *)(\\S.*)/', $line, $match)) {
                $len = strlen($match[1]);
                if ($len % self::DEFAULT_INDENT == 0) {
                    $level = $len / self::DEFAULT_INDENT;
                    $line = str_repeat(' ', $level * $indent) . $match[2];
                }
            }
            $newLines[] = $line;
        }
        $source = implode("\n", $newLines);
        return $source;
    }

    /**
     * Format (pretty print) some source code.
     *
     * @param string $source
     *
     * @return string
     */
    abstract public function format($source);

    /** Process files. */
    public function process()
    {
        foreach ($this->operands as $path) {
            if (is_dir($path)) {
                $cmd = 'find %s -type f';
                $files = $this->runCommand($cmd, $path);
                $re = '\\.(' . implode('|', $this->exts) . ')$';
                sort($files);
                foreach ($files as $file) {
                    if (preg_match('/' . $re . '/', $file)) {
                        $this->processFile($file);
                    }
                }
            } else {
                $this->processFile($path);
            }
        }
    }

    /**
     * Set a configuration option.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * Set the project directory.
     *
     * @param string $projectDir
     */
    public function setProjectDir($projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Process a single file.
     *
     * @param string $inFile
     */
    protected function processFile($inFile)
    {
        if ($inFile == 'php://stdin') {
            $outFile = 'php://stdout';
        } else {
            if (!file_exists($inFile)) {
                die("File {$inFile} not found\n");
            }

            // Write filename to stderr.
            error_log($inFile);

            // Back up file.
            $outFile = $inFile;
            copy($outFile, $outFile . '.bak');
        }

        // Process the input.
        $source = file_get_contents($inFile);
        if ($source) {
            try {
                $pretty = $this->format($source);
                if ($pretty) {
                    file_put_contents($outFile, $pretty);
                } else {
                    error_log('Error in ' . $inFile . ': No output received');
                }
            } catch (\Exception $e) {
                error_log('Exception in ' . $inFile . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Run a command on an argument.
     *
     * @param string $command
     * @param string $arg
     *
     * @return array
     */
    protected function runCommand($command, $arg)
    {
        $output = [];
        $command = sprintf($command, escapeshellarg($arg));
        exec($command, $output, $returnCode);
        if ($returnCode) {
            error_log("Error running {$command} on {$arg}: {$returnCode}");
            die;
        }
        return $output;
    }

    /**
     * Trim extra space at the beginning/end of the block and the end of each
     * line.
     *
     * @param string $source
     *
     * @return string
     */
    protected function trimExtraSpace($source)
    {
        // Fix indents to be multiples of default and remove extra space at line end.
        $lines = explode("\n", $source);
        $newLines = [];
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/^( +)(\\S.*)/', $line, $match)) {
                $indent = $match[1];
                $rest = $match[2];

                // Exclude * from adjustment so docblocks aren't messed up.
                if (substr($rest, 0, 1) != '*') {
                    $len = strlen($indent);
                    if ($len % self::DEFAULT_INDENT == 1) {
                        $len--;
                    } elseif ($len % self::DEFAULT_INDENT == self::DEFAULT_INDENT - 1) {
                        $len++;
                    }
                    $line = str_repeat(' ', $len) . ltrim($line);
                }
            }
            $newLines[] = $line;
        }
        $source = implode("\n", $newLines);

        // Trim block space.
        $source = trim($source);
        return $source;
    }

    /**
     * Wrap comment blocks.
     *
     * @param string $source
     *
     * @return string
     */
    protected function wrapComments($source)
    {
        if (preg_match_all('~(?<=\\n) */\\*.*?\\*/ *\\n~s', $source, $matches)) {
            foreach ($matches[0] as $block) {
                $pretty = $this->wrapMultiline($block);
                $source = str_replace($block, $pretty, $source);
            }
        }
        if (preg_match_all('~(?<=\\n)( *//.*\\n)+~', $source, $matches)) {
            foreach ($matches[0] as $block) {
                $pretty = $this->wrapSingleLine($block);
                $source = str_replace($block, $pretty, $source);
            }
        }
        return $source;
    }

    /**
     * Wrap a multiline comment block.
     *
     * @param string $block
     *
     * @return string
     */
    protected function wrapMultiline($block)
    {
        $lines = explode("\n", rtrim($block));
        preg_match('~^( *)(/\\*+)~', $lines[0], $match);
        $indent = $match[1];
        $text = '';
        $prevTag = '';
        foreach ($lines as $line) {
            // Remove comment marks.
            $line = preg_replace('~\\*/ *$~', '', $line);
            $line = preg_replace('~^ *(\\/\\*+|\\*)~', '', $line);
            $line = preg_replace('~\\s+~', ' ', $line);

            // Put @tags on new line.
            if (preg_match('/^\\s*@(\\w+)/', $line, $match)) {
                $tag = $match[1];

                // Add extra line when switching tags.
                if ($text && $tag != $prevTag) {
                    $text .= "\n";
                }
                $prevTag = $tag;
                $text .= "\n";
            }
            $text .= trim($line) . ' ';
        }
        $text = trim(wordwrap($text));
        $newLines = explode("\n", $text);
        if (count($newLines) == 1) {
            $pretty = $indent . '/** ' . $newLines[0] . ' */' . "\n";
            return $pretty;
        }
        $pretty = $indent . '/**' . "\n";
        foreach ($newLines as $line) {
            $pretty .= $indent . ' * ' . trim($line) . "\n";
        }
        $pretty .= $indent . ' */' . "\n";
        return $pretty;
    }

    /**
     * Wrap a single-line comment block.
     *
     * @param string $block
     *
     * @return string
     */
    protected function wrapSingleLine($block)
    {
        $lines = explode("\n", $block);
        preg_match('~^( *)//~', $lines[0], $match);
        $indent = $match[1];
        $text = '';
        foreach ($lines as $line) {
            $line = preg_replace('~^ *//~', '', $line);
            $line = preg_replace('~\\s+~', ' ', $line);
            $text .= trim($line) . ' ';
        }
        $text = trim(wordwrap($text));
        $newLines = explode("\n", $text);
        $pretty = '';
        foreach ($newLines as $line) {
            $pretty .= $indent . '// ' . $line . "\n";
        }
        return $pretty;
    }
}
