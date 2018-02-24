<?php
namespace Tidy;

/** Format SQL. */
class SqlPrinter extends Printer
{
    /**
     * Format (pretty print) some SQL.
     *
     * @param string $source
     *
     * @return string
     */
    public function format($source)
    {
        $indent = $this->options['indent'];
        $queries = preg_split('/;\s*\n/', $source, -1, PREG_SPLIT_NO_EMPTY);
        $pretty = '';
        $count = count($queries);
        foreach ($queries as $query) {
            $newQuery = \PhpMyAdmin\SqlParser\Utils\Formatter::format($query, array('type' => 'text'));
            $lines = explode("\n", $newQuery);
            $newLines = [];
            foreach ($lines as $line) {
                $line = $this->fixPhpVars($line);
                $line = $this->splitLong($line);
                $line = $this->indentComments($line);
                $newLines[] = rtrim($line);
            }
            $newQuery = implode("\n", $newLines);
            $newQuery = $this->fixQuery($newQuery);
            $pretty .= $newQuery;
            if ($count > 1) {
                $pretty .= ";\n\n";
            }
        }
        $pretty = $this->trimExtraSpace($pretty);
        $pretty = $this->fixIndent($pretty, $indent);
        return $pretty;
    }

    /**
     * Fix PHP variable references.
     *
     * @param string $line
     *
     * @return string
     */
    protected function fixPhpVars($line)
    {
        // Remove extra spaces from PHP variable references.
        if (preg_match_all('/{ *\$ *\w.*?}/', $line, $matches)) {
            foreach ($matches[0] as $match) {
                $ref = preg_replace('/\s+/', '', $match);
                $line = str_replace($match, $ref, $line);
            }
        }
        return $line;
    }

    /**
     * Fix various SQL issues with formatted query.
     *
     * @param string $query
     * @return string
     */
    protected function fixQuery($query)
    {
        $result = $query;

        // USE commands must be on one line.
        $result = preg_replace('/\bUSE\s+(\w+)/', 'USE \1', $result);
        return $result;
    }

    /**
     * Indent comments at start of line.
     *
     * @param string $line
     *
     * @return string
     */
    protected function indentComments($line)
    {
        $line = preg_replace('/^ +(#|--)/', '\\1', $line);
        return $line;
    }

    /**
     * Split long lines.
     *
     * @param string $line
     *
     * @return string
     */
    protected function splitLong($line)
    {
        // Break up long lines that have embedded AND|OR.
        if (strlen($line) <= 80 || preg_match('/^\s+(#|--)/', $line)) {
            return $line;
        }
        $indent = str_repeat(' ', self::DEFAULT_INDENT);
        if (preg_match('/^( *)(.*)/', $line, $match)) {
            $whitespace = $match[1];
            $rest = $match[2];
            $parts = preg_split('/ (AND|OR) /', $rest, -1, PREG_SPLIT_DELIM_CAPTURE);
            $count = count($parts);
            if ($count > 1 && $count % 2 == 1) {
                $newLine = $whitespace . $parts[0] . "\n";
                for ($i = 1; $i < $count - 1; $i += 2) {
                    $newLine .= sprintf("%s%s%s %s\n", $whitespace, $indent, $parts[$i], $parts[$i + 1]);
                }
                $line = $newLine;
            }
        }
        return $line;
    }
}
