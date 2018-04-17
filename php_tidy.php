#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

// Process options.
$options = getopt('ci:');
$indent = isset($options['i']) ? $options['i'] : \Tidy\Printer::DEFAULT_INDENT;

// Process input.
$tidy = new \Tidy\PhpPrinter();
$tidy->setProjectDir(__DIR__);
$tidy->addOperands($argv);
$tidy->setOption('indent', $indent);
$tidy->setOption('wrapComments', isset($options['c']));
$tidy->addExt('php');
$tidy->process();
