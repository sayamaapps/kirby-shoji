<?php

/**
 * Router of the local server for HttpStreamTest (php -S): a few fixed answers
 * for Http::stream().
 *
 *   /image     200 and a few bytes that start like a JPEG
 *   /redirect  302 to /image
 *   /missing   404 with a body
 *   /nobody    200 without a body
 *   /limit     200 and exactly Http::MAX_BYTES bytes
 *   /toobig    200 and one byte more
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
	case '/image':
		header('Content-Type: image/jpeg');
		echo "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000);
		break;
	case '/redirect':
		header('Location: /image', true, 302);
		break;
	case '/missing':
		http_response_code(404);
		echo 'not here';
		break;
	case '/nobody':
		header('Content-Type: image/jpeg');
		break;
	case '/limit':
	case '/toobig':
		echo str_repeat('x', 5_000_000 + ($path === '/toobig' ? 1 : 0));
		break;
	default:
		http_response_code(500);
}
