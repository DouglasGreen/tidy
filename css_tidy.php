#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/cerdic/css-tidy/class.csstidy.php';

// Process options.
$options = getopt('i:ps');
$indent = isset($options['i']) ? $options['i'] : \Tidy\Printer::DEFAULT_INDENT;

// Process input.
$tidy = new \Tidy\CssPrinter();
$tidy->addOperands($argv);
$tidy->setOption('indent', $indent);
$tidy->setOption('sortProperties', isset($options['p']));
$tidy->setOption('sortSelectors', isset($options['s']));
$tidy->addExt('css');
$tidy->process();
