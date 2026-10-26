<?php

/**
 * Class map for Kirby's F::loadClasses(). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\shoji\\embed' => 'src/Embed.php',
	'sayamaapps\\shoji\\http' => 'src/Http.php',
	'sayamaapps\\shoji\\i18n' => 'src/I18n.php',
	'sayamaapps\\shoji\\options' => 'src/Options.php',
	'sayamaapps\\shoji\\providers' => 'src/Providers.php',
	'sayamaapps\\shoji\\signer' => 'src/Signer.php',
	'sayamaapps\\shoji\\thumbs' => 'src/Thumbs.php',
];
