<?php

use Kirby\Text\KirbyTag;
use Sayamaapps\Shoji\Api;

// (video: …) — a YouTube or Vimeo address becomes a placeholder (Api::tag()). The
// attribute list is Kirby's, so existing tags keep parsing.
return [
	'video' => [
		'attr' => [
			'autoplay', 'caption', 'controls', 'class', 'disablepictureinpicture', 'height',
			'loop', 'muted', 'playsinline', 'poster', 'preload', 'style', 'width',
		],
		'html' => fn (KirbyTag $tag): string => Api::tag($tag),
	],
];
