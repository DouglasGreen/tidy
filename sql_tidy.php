#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

// Process options.
$options = getopt('i:');
$indent = isset($options['i']) ? $options['i'] : \Tidy\Printer::DEFAULT_INDENT;

// Process input.
$tidy = new \Tidy\SqlPrinter();
$tidy->addOperands($argv);
$tidy->setOption('indent', $indent);
$tidy->addExt('sql');
$tidy->process();
