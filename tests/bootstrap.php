<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ShojiTestCase.php';

// The plugin's classes come from the class map, as in production.
Kirby\Filesystem\F::loadClasses(require dirname(__DIR__) . '/autoload.php', dirname(__DIR__));

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;
