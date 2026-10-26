<?php

use Kirby\Cms\App;
use Sayamaapps\Shoji\Api;

return [
	[
		'pattern' => 'shoji/(thumb|frame|script|style|html|go)/(:any)/(:any)',
		'method'  => 'GET',
		'action'  => fn (string $kind, string $provider, string $id) => Api::route(App::instance(), $kind, $provider, $id),
	],
];
