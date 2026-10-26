<?php

// The placeholder and the video block. A site with its own markup creates
// site/snippets/shoji/embed.php (Kirby looks there first).
return [
	'shoji/embed'    => dirname(__DIR__) . '/snippets/shoji/embed.php',
	'shoji/fallback' => dirname(__DIR__) . '/snippets/shoji/fallback.php',
	'blocks/video'   => dirname(__DIR__) . '/snippets/blocks/video.php',
];
