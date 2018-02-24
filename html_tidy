#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

// Process options.
$options = getopt('ahi:');
$indent = isset($options['i']) ? $options['i'] : \Tidy\Printer::DEFAULT_INDENT;

// Process input.
$tidy = new \Tidy\HtmlPrinter();
$tidy->setProjectDir(__DIR__);
$tidy->addOperands($argv);
$tidy->setOption('indent', $indent);
$tidy->setOption('showHead', isset($options['h']));
$tidy->setOption('sortAttributes', isset($options['a']));
$tidy->addExt('htm');
$tidy->addExt('html');
$tidy->addExt('xhtml');
$tidy->process();
