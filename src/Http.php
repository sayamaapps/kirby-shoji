<?php

namespace Sayamaapps\Shoji;

use Closure;

/**
 * Server-side GET for preview images (and Vimeo's oEmbed answer). Uses curl
 * when available, otherwise PHP's stream wrapper. Sends no visitor data: no
 * cookies, no forwarded IP, no referrer, a fixed user agent.
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Http
{
	public const MAX_BYTES = 5_000_000;
	public const TIMEOUT = 5;
	public const USER_AGENT = 'Mozilla/5.0 (compatible; kirby-shoji/1.2; +https://github.com/sayamaapps/kirby-shoji)';

	/**
	 * 1.x test hook that replaces the network: fn (string $url): array{0:int,1:string}.
	 *
	 * @deprecated 1.x test hook, kept until the next major version. Pass a fetcher to Thumbs instead.
	 * @internal
	 */
	public static Closure|null $fake = null;

	/** @return array{0:int,1:string} */
	private static function curl(string $url): array
	{
		$handle = curl_init($url);
		if ($handle === false) {
			return [0, ''];
		}
		$body = '';
		curl_setopt_array($handle, [
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
			CURLOPT_TIMEOUT        => self::TIMEOUT,
			CURLOPT_USERAGENT      => self::USER_AGENT,
			CURLOPT_WRITEFUNCTION  => function ($handle, string $chunk) use (&$body): int {
				$body .= $chunk;
				return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk); // 0 aborts
			},
		]);
		$ok = curl_exec($handle);
		$status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		// No curl_close(): a no-op since PHP 8.0, deprecated in 8.5.
		return $ok === false ? [0, ''] : [$status, $body];
	}

	/** @return array{0:int,1:string} status (0 = no answer) and body */
	public static function get(string $url): array
	{
		if (self::$fake !== null) {
			return (self::$fake)($url);
		}
		if (!str_starts_with($url, 'https://')) {
			return [0, ''];
		}
		return function_exists('curl_init') ? self::curl($url) : self::stream($url);
	}

	/** @return array{0:int,1:string} */
	private static function stream(string $url): array
	{
		$context = stream_context_create(['http' => [
			'method'          => 'GET',
			'timeout'         => self::TIMEOUT,
			'follow_location' => 1,
			'max_redirects'   => 3,
			'ignore_errors'   => true,
			'user_agent'      => self::USER_AGENT,
		]]);
		$stream = Quiet::run(fn () => fopen($url, 'rb', false, $context));
		if ($stream === false) {
			return [0, ''];
		}
		$body = Quiet::run(fn () => stream_get_contents($stream, self::MAX_BYTES + 1));
		// The response headers come from the stream itself, on every PHP version.
		// $http_response_header exists only in the scope that called
		// file_get_contents() and only where the name is written out (it is
		// deprecated in 8.5 for that reason); http_get_last_response_headers()
		// exists only since 8.4. With redirects the list holds every response.
		$headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
		fclose($stream);
		// The last status line counts (after redirects).
		$status = 0;
		foreach (is_array($headers) ? $headers : [] as $line) {
			if (is_string($line) && preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
				$status = (int)$m[1];
			}
		}
		if ($body === false || strlen($body) > self::MAX_BYTES) {
			return [0, ''];
		}
		return [$status, $body];
	}
}
