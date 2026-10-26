<?php

/**
 * Names of the 1.x API, kept until the next major version (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases only
 * for now; no logic here.
 */

class_alias(\Sayamaapps\Shoji\Embed::class, 'Shoji\\Embed');
class_alias(\Sayamaapps\Shoji\Http::class, 'Shoji\\Http');
class_alias(\Sayamaapps\Shoji\I18n::class, 'Shoji\\I18n');
class_alias(\Sayamaapps\Shoji\Options::class, 'Shoji\\Options');
class_alias(\Sayamaapps\Shoji\Providers::class, 'Shoji\\Providers');
class_alias(\Sayamaapps\Shoji\Signer::class, 'Shoji\\Signer');
class_alias(\Sayamaapps\Shoji\Thumbs::class, 'Shoji\\Thumbs');
