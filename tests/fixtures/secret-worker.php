<?php

namespace Sayamaapps\Shoji;

/**
 * A second process for SignerSecretTest: reads the stored secret of a
 * directory and reports what it got and how it signed a sample address.
 *
 * Usage: php secret-worker.php <name> <secret directory> <control directory> <pause>
 *
 * With <pause> = 1 the worker stops once, right after is_file() told it the
 * secret is missing, and waits for the file `release-<name>` in the control
 * directory. That reproduces two first requests that both saw no secret. The
 * namespaced is_file() below is the only seam; src/Signer.php is unchanged.
 */
[, $name, $directory, $control, $pause] = $argv;

/** Whether the pause is still to come. */
$GLOBALS['shoji-worker-pause'] = $pause === '1';

function is_file(string $path): bool
{
	global $control, $directory, $name;
	$answer = \is_file($path);
	if (!$answer && $path === $directory . '/secret' && $GLOBALS['shoji-worker-pause']) {
		$GLOBALS['shoji-worker-pause'] = false;
		file_put_contents($control . '/ready-' . $name, 'observed missing');
		$deadline = microtime(true) + 10;
		while (!\is_file($control . '/release-' . $name)) {
			if (microtime(true) > $deadline) {
				fwrite(STDERR, 'no release in time');
				exit(2);
			}
			usleep(1000);
		}
	}
	return $answer;
}

require dirname(__DIR__, 2) . '/src/Quiet.php';
require dirname(__DIR__, 2) . '/src/Signer.php';

$secret = Signer::storedSecret($directory);
file_put_contents($control . '/returned-' . $name, json_encode([
	'secret' => $secret,
	'signature' => (new Signer($secret))->sign('frame', 'youtube', 'aaaaaaaaaaa'),
]));
