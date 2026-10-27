<?php

use Kirby\Cms\App;
use Kirby\Filesystem\F;

/**
 * Shoji (障子, the paper sliding screen: you see the outline, the outside
 * comes in only when you slide it open): third-party content behind a
 * placeholder until the visitor clicks.
 *
 * Kirby loads this file. It registers the class map and the legacy names, then
 * the definitions in config/. The logic lives in src/. Off by default: without
 * `'shoji.active' => true` the snippet prints a plain link, the video block and
 * the (video:) tag behave exactly like Kirby's, and the routes answer 404.
 *
 * Nothing in the page names the provider's host: the preview image, the iframe,
 * the markup of kind 'html', extra scripts and stylesheets and the plain link
 * all point to this site (/shoji/…, signed) and are redirected or served from there.
 */
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

App::plugin(
	name: 'sayamaapps/shoji',
	extends: [
		'translations' => require __DIR__ . '/config/translations.php',
		'snippets'     => require __DIR__ . '/config/snippets.php',
		'siteMethods'  => require __DIR__ . '/config/site-methods.php',
		'routes'       => require __DIR__ . '/config/routes.php',
		'tags'         => require __DIR__ . '/config/tags.php',
	],
	root: __DIR__
);
