<?php

use Sayamaapps\Shoji\Options;

return [
	// $site->shojiOpen('brevo'): true if this provider's content loads with the page.
	'shojiOpen' => function (string $provider): bool {
		return Options::opens($this->kirby(), $provider);
	},
];
