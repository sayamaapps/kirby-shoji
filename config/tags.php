<?php

use Kirby\Text\KirbyTag;
use Sayamaapps\Shoji\Api;

// (video: …) — a YouTube or Vimeo address becomes a placeholder (Api::tag()). The
// attribute list is Kirby 6's: without `style`, which let content set CSS.
return [
	'video' => [
		'attr' => [
			'autoplay', 'caption', 'controls', 'class', 'disablepictureinpicture', 'height',
			'loop', 'muted', 'playsinline', 'poster', 'preload', 'width',
		],
		'html' => fn (KirbyTag $tag): string => Api::tag($tag),
	],
];
