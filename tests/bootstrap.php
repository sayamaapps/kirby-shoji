<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

require_once __DIR__ . '/helpers.php';

// The plugin's classes, as index.php loads them.
foreach (glob(dirname(__DIR__) . '/src/*.php') as $file) {
	require_once $file;
}

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;
