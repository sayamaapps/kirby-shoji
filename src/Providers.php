<?php

namespace Sayamaapps\Shoji;

use Closure;

/**
 * The providers Shoji knows: how to recognise an address, what a valid id
 * looks like, and where the embed, the plain link, an extra script and the
 * preview image live.
 *
 * A definition:
 *
 *   'name'       => 'YouTube',             shown in the note and the button
 *   'company'    => 'Google',              who learns the visitor's IP address
 *   'kind'       => 'video',               'video' | 'form' | 'content' | 'html' (picks the texts)
 *   'id'         => '/^…$/',               a valid id, checked on render and in every route
 *   'match'      => '~…(id)…~i',           optional: finds the id (first group) in a pasted address
 *   'urlParams'  => fn (string $url, array $match): array|null
 *                                          optional: the parameters a pasted address carries besides
 *                                          the id (Vimeo's h, Digistore24's form, aff, cam); null: a
 *                                          form of the address this provider cannot carry, so it is
 *                                          not taken over (the next provider is tried)
 *   'embed'      => 'https://…/{id}',      the iframe address (not for kind 'html')
 *   'snippet'    => 'forms/{id}',          kind 'html' only: the site's Kirby snippet with the markup
 *   'link'       => 'https://…/{id}',      optional: the plain link (without JavaScript), default: embed
 *   'script'     => 'https://….js',        optional: loaded before the iframe (kind 'html': after the
 *                                          markup is in the page); one address or a list, in order
 *   'style'      => 'https://….css',       optional, kind 'html': stylesheets, one address or a list
 *   'thumbnails' => ['https://…/{id}.jpg'] optional: preview image candidates, or a
 *                                          closure fn (string $id, Closure $fetch, array $params): array
 *   'size'       => [1280, 720],           optional: width/height of the preview image
 *   'allow'      => 'autoplay; fullscreen' optional: features the iframe may use
 *
 * Address templates know three placeholders: {id}, {name} (the value of the
 * parameter `name`, empty if missing) and {/name} (a slash plus the value, or
 * nothing). Parameters no placeholder uses are appended as the query string.
 * Templates must produce an https address; anything else is refused.
 *
 * A pasted address gives the id and, through `urlParams`, parameters; those
 * given explicitly next to it win (Embed::fromUrl()).
 *
 * `shoji.providers` adds providers, changes single fields of a built-in one,
 * or removes one with `false`:
 *
 *   'shoji.providers' => [
 *       'vimeo'   => false,
 *       'youtube' => ['embed' => 'https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0'],
 *       'spotify' => ['name' => 'Spotify', …],
 *   ]
 *
 * `shoji.open` loads a provider's content with the page instead of after
 * the click (see open()):
 *
 *   'shoji.open' => ['brevo' => true]
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Providers
{
	/** At most this many scripts and stylesheets per provider (the index travels in the address). */
	public const ASSETS_MAX = 10;

	/** Kinds a definition may name. 'html': the site's own markup, inserted after the click. */
	public const KINDS = ['video', 'form', 'content', 'html'];
	public const NAME = '/^[a-z][a-z0-9-]{0,31}$/';
	public const PARAM_NAME = '/^[A-Za-z][A-Za-z0-9_]{0,31}$/';
	public const PARAM_VALUE_MAX = 200;

	/**
	 * The providers Shoji protects, by the hosts they live on (a host and
	 * every subdomain of it). While Shoji is on, an address on one of these
	 * hosts never reaches Kirby's own video output, which would print an
	 * iframe or a <video> that loads before the click — whether or not
	 * Shoji recognises the address (see isProtectedHost()). The name is
	 * shown in the note.
	 */
	public const PROTECTED = [
		'youtube'   => ['name' => 'YouTube', 'hosts' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com']],
		'vimeo'     => ['name' => 'Vimeo', 'hosts' => ['vimeo.com']],
		'digistore' => ['name' => 'Digistore24', 'hosts' => ['digistore24.com']],
	];

	/** Every id, whatever the provider says, must also be safe as a path segment and file name. */
	public const SAFE_ID = '/^[A-Za-z0-9_-]{1,64}$/';

	/** A snippet name after {id} is filled: path segments, no dots, so nothing outside the snippet roots. */
	public const SNIPPET = '~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~';

	/** Problems with the addresses, scripts, stylesheets and thumbnails of a definition, or null. */
	private static function addressProblem(array $definition): string|null
	{
		foreach (['embed', 'link'] as $key) {
			if (isset($definition[$key]) && (!is_string($definition[$key]) || !str_starts_with($definition[$key], 'https://'))) {
				return "'$key' must be an https address";
			}
		}
		foreach (['script', 'style'] as $key) {
			$list = self::assets($definition, $key);
			if ($list === null || count($list) > self::ASSETS_MAX) {
				return "'$key' must be an https address or a list of up to " . self::ASSETS_MAX;
			}
		}
		$thumbnails = $definition['thumbnails'] ?? [];
		if (!$thumbnails instanceof Closure && !is_array($thumbnails)) {
			return "'thumbnails' must be a list or a closure";
		}
		return null;
	}

	/**
	 * Built-in providers merged with `shoji.providers`. Invalid definitions
	 * are dropped and logged, so a typo in the configuration never renders a
	 * broken embed.
	 *
	 * @return array<string, array>
	 */
	public static function all(mixed $configured = null): array
	{
		$providers = self::defaults();
		foreach (is_array($configured) ? $configured : [] as $name => $definition) {
			if ($definition === false) {
				unset($providers[$name]);
				continue;
			}
			if (!is_string($name) || !preg_match(self::NAME, $name) || !is_array($definition)) {
				error_log('Shoji: ignoring provider ' . var_export($name, true) . ' (name must match ' . self::NAME . ', definition must be an array)');
				continue;
			}
			$providers[$name] = array_replace($providers[$name] ?? [], $definition);
		}
		foreach ($providers as $name => $definition) {
			if (($problem = self::problem($definition)) !== null) {
				error_log("Shoji: ignoring provider $name: $problem");
				unset($providers[$name]);
			}
		}
		return $providers;
	}

	/**
	 * Value of the iframe `allow` attribute. The iframe first loads Shoji's
	 * own /shoji/frame/… address and is redirected to the provider from
	 * there, so the default allowlist ('src' = our own origin) would not
	 * cover the provider: every feature gets `*`.
	 */
	public static function allow(array $definition): string
	{
		$features = array_filter(array_map('trim', explode(';', (string)($definition['allow'] ?? ''))));
		$features = array_filter($features, fn (string $f) => preg_match('/^[a-z-]+$/', $f) === 1);
		return implode('; ', array_map(fn (string $f) => $f . ' *', $features));
	}

	/**
	 * The provider's scripts or stylesheets (`script`, `style`) as a list,
	 * in order; null if the field is not an https address or a list of them.
	 *
	 * @return list<string>|null
	 */
	public static function assets(array $definition, string $key): array|null
	{
		$value = $definition[$key] ?? [];
		$list = is_string($value) ? [$value] : $value;
		if (!is_array($list) || !array_is_list($list)) {
			return null;
		}
		foreach ($list as $url) {
			if (!is_string($url) || !str_starts_with($url, 'https://')) {
				return null;
			}
		}
		return $list;
	}

	/** @return array<string, array> */
	public static function defaults(): array
	{
		return [
			'youtube' => [
				'name'       => 'YouTube',
				'company'    => 'Google',
				'kind'       => 'video',
				// Not "videoseries": that is the playlist player (embed/videoseries?list=…), no video.
				'id'         => '/^(?!videoseries$)[A-Za-z0-9_-]{11}$/',
				'match'      => '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i',
				'embed'      => 'https://www.youtube-nocookie.com/embed/{id}?autoplay=1',
				'link'       => 'https://www.youtube.com/watch?v={id}',
				'thumbnails' => [
					'https://img.youtube.com/vi/{id}/maxresdefault.jpg',
					'https://img.youtube.com/vi/{id}/hqdefault.jpg',
				],
				'size'       => [1280, 720],
				'allow'      => 'autoplay; encrypted-media; fullscreen; picture-in-picture',
			],
			// A playlist (youtube.com/playlist?list=…, embed/videoseries?list=…): the player
			// of the list, no preview image (YouTube has no fixed address for one).
			'youtube-playlist' => [
				'name'    => 'YouTube',
				'company' => 'Google',
				'kind'    => 'video',
				'id'      => '/^[A-Za-z0-9_-]{13,64}$/',
				'match'   => '~youtube(?:-nocookie)?\.com/(?:playlist|embed/videoseries)\?(?:[^#]*&)?list=([A-Za-z0-9_-]{13,64})(?![A-Za-z0-9_-])~i',
				'embed'   => 'https://www.youtube-nocookie.com/embed/videoseries?list={id}&autoplay=1',
				'link'    => 'https://www.youtube.com/playlist?list={id}',
				'allow'   => 'autoplay; encrypted-media; fullscreen; picture-in-picture',
			],
			'vimeo' => [
				'name'       => 'Vimeo',
				'company'    => 'Vimeo',
				'kind'       => 'video',
				'id'         => '/^\d{6,12}$/',
				'match'      => '~vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d{6,12})(?![\w/])~i',
				// h: the hash of an unlisted video, ?h= on the player and /{id}/{h} on vimeo.com
				'urlParams'  => static function (string $url): array|null {
					$h = self::query($url)['h'] ?? null;
					if ($h === null || $h === '') {
						return [];
					}
					return is_string($h) && preg_match('/^[A-Za-z0-9]{6,32}$/', $h) === 1 ? ['h' => $h] : null;
				},
				'embed'      => 'https://player.vimeo.com/video/{id}?autoplay=1&dnt=1',
				'link'       => 'https://vimeo.com/{id}{/h}',
				// Vimeo has no fixed image address; its oEmbed answer names one. An unlisted
				// video needs its full address (with the hash) there, too.
				'thumbnails' => static function (string $id, Closure $fetch, array $params = []): array {
					$page = 'https://vimeo.com/' . $id . (isset($params['h']) && preg_match(self::SAFE_ID, $params['h']) ? '/' . $params['h'] : '');
					[$status, $body] = $fetch('https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode($page));
					$data = $status === 200 ? json_decode($body, true) : null;
					$url = is_array($data) ? ($data['thumbnail_url'] ?? null) : null;
					return is_string($url) && str_starts_with($url, 'https://') ? [$url] : [];
				},
				'size'       => [1280, 720],
				'allow'      => 'autoplay; fullscreen; picture-in-picture',
			],
			'digistore' => [
				'name'    => 'Digistore24',
				'company' => 'Digistore24',
				'kind'    => 'form',
				'id'      => '/^\d{1,10}$/',
				'match'   => '~digistore24\.com/(?:product|redir)/(\d{1,10})(?!\d)~i',
				// product/{id}/{form}: the order form; ?aff= and ?cam=: affiliate and campaign.
				// redir/{id}/… carries other things in its path (affiliate, campaign): not taken over.
				'urlParams' => static function (string $url): array|null {
					if (preg_match('~digistore24\.com/(product|redir)/\d{1,10}((?:/[^/?#]*)*)~i', $url, $m) !== 1) {
						return null;
					}
					$segments = array_values(array_filter(explode('/', $m[2]), fn (string $s) => $s !== ''));
					$params = [];
					if (strtolower($m[1]) === 'product' && count($segments) === 1 && preg_match('/^\d{1,10}$/', $segments[0])) {
						$params['form'] = $segments[0];
					} elseif ($segments !== []) {
						return null;
					}
					$query = self::query($url);
					foreach (['aff', 'cam'] as $name) {
						$value = $query[$name] ?? '';
						if ($value === '') {
							continue;
						}
						if (!is_string($value) || strlen($value) > self::PARAM_VALUE_MAX) {
							return null;
						}
						$params[$name] = $value;
					}
					return $params;
				},
				// {/form}: the order form id, as in …/product/123456/78901
				'embed'   => 'https://www.digistore24.com/product/{id}{/form}',
				'script'  => 'https://www.digistore24-scripts.com/service/digistore.js',
				'allow'   => 'payment',
			],
		];
	}

	/**
	 * Fills an address template. {name} and {/name} only take values that
	 * are safe as a path segment; others count as missing. Unused parameters
	 * become the query string.
	 */
	public static function fill(string $template, string $id, array $params): string
	{
		$used = [];
		$url = (string)preg_replace_callback('~\{(/?)([A-Za-z][A-Za-z0-9_]*)\}~', function (array $m) use ($id, $params, &$used) {
			[, $slash, $name] = $m;
			if ($name === 'id') {
				return $slash . rawurlencode($id);
			}
			$used[$name] = true;
			$value = $params[$name] ?? null;
			if (!is_string($value) || !preg_match(self::SAFE_ID, $value)) {
				return '';
			}
			return $slash . $value;
		}, $template);
		$rest = array_diff_key($params, $used);
		if ($rest !== []) {
			$url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($rest, '', '&', PHP_QUERY_RFC3986);
		}
		return $url;
	}

	/** True if $url names a host of a protected provider, recognised by Shoji or not. */
	public static function isProtectedHost(string $url): bool
	{
		return self::protectedProvider($url) !== null;
	}

	/**
	 * The per-provider switch `shoji.open`: provider name => true (the
	 * content loads with the page, no placeholder) or false (behind the
	 * click, as without the option). Only names in $providers (built-in and
	 * `shoji.providers`, after merging) and boolean values are taken; the
	 * rest is dropped and logged, so a typo never opens anything. There is
	 * deliberately no switch for every provider at once.
	 *
	 * @param array<string, array> $providers Providers::all()
	 * @return array<string, bool>
	 */
	public static function open(mixed $configured, array $providers): array
	{
		if ($configured === null) {
			return [];
		}
		if (!is_array($configured)) {
			error_log('Shoji: ignoring shoji.open (must be an array of provider name => true|false)');
			return [];
		}
		$open = [];
		foreach ($configured as $name => $value) {
			if (!is_string($name) || !isset($providers[$name])) {
				error_log('Shoji: ignoring shoji.open for ' . var_export($name, true) . ' (no such provider)');
				continue;
			}
			if (!is_bool($value)) {
				error_log("Shoji: ignoring shoji.open for $name (value must be true or false)");
				continue;
			}
			$open[$name] = $value;
		}
		return $open;
	}

	/**
	 * Keeps only well-formed parameters: names like `form` or `aff`, scalar
	 * values up to 200 characters. Sorted by name, so the signature does not
	 * depend on the order a template passed them in.
	 *
	 * @return array<string,string>
	 */
	public static function params(mixed $params): array
	{
		$clean = [];
		foreach (is_array($params) ? $params : [] as $name => $value) {
			if (!is_string($name) || !preg_match(self::PARAM_NAME, $name)) {
				continue;
			}
			if (is_int($value) || is_float($value)) {
				$value = (string)$value;
			}
			if (!is_string($value) || $value === '' || strlen($value) > self::PARAM_VALUE_MAX) {
				continue;
			}
			$clean[$name] = $value;
		}
		ksort($clean);
		return $clean;
	}

	/** Problems with the id, the match, the urlParams and the snippet of a definition, or null. */
	private static function patternProblem(array $definition, bool $html): string|null
	{
		if (Quiet::run(fn () => preg_match($definition['id'], '')) === false) {
			return "'id' is no valid regular expression";
		}
		if (isset($definition['match']) && (!is_string($definition['match']) || $definition['match'] === '' || Quiet::run(fn () => preg_match($definition['match'], '')) === false)) {
			return "'match' is no valid regular expression";
		}
		if (isset($definition['urlParams']) && !$definition['urlParams'] instanceof Closure) {
			return "'urlParams' must be a closure";
		}
		if ($html && !preg_match(self::SNIPPET, str_replace('{id}', 'id', (string)$definition['snippet']))) {
			return "'snippet' must be a snippet name like 'forms/{id}'";
		}
		return null;
	}

	/**
	 * The address for the plain link that stands in for an address Shoji
	 * cannot embed: the pasted address itself, https for a scheme-relative
	 * one; null unless it is an http(s) address on a protected host without
	 * whitespace or control characters (never a javascript: link).
	 */
	public static function plainLink(string $url): string|null
	{
		$url = trim($url);
		if (str_starts_with($url, '//')) {
			$url = 'https:' . $url;
		}
		return preg_match('~^https?://[^\s\x00-\x1f\x7f]+$~i', $url) === 1 && self::isProtectedHost($url) ? $url : null;
	}

	/** What is wrong with a definition, or null. */
	public static function problem(array $definition): string|null
	{
		$html = ($definition['kind'] ?? null) === 'html';
		foreach (['name', 'company', 'id', $html ? 'snippet' : 'embed'] as $key) {
			if (!is_string($definition[$key] ?? null) || trim($definition[$key]) === '') {
				return "'$key' is missing";
			}
		}
		return self::patternProblem($definition, $html) ?? self::addressProblem($definition);
	}

	/**
	 * Key of the protected provider whose host $url names (a key of
	 * PROTECTED), or null. Only the host counts, so `notyoutube.com` and
	 * `youtube.com.example.org` are not protected, and the scheme does not:
	 * Kirby builds its iframe from any scheme, and so does this check.
	 */
	public static function protectedProvider(string $url): string|null
	{
		$host = parse_url(trim($url), PHP_URL_HOST);
		if (!is_string($host) || $host === '') {
			return null;
		}
		$host = strtolower(rtrim($host, '.'));
		foreach (self::PROTECTED as $key => $provider) {
			foreach ($provider['hosts'] as $domain) {
				if ($host === $domain || str_ends_with($host, '.' . $domain)) {
					return $key;
				}
			}
		}
		return null;
	}

	/**
	 * The query string of an address as an array (names and values decoded,
	 * the last of a repeated name wins, `name[]=…` an array); empty without one.
	 */
	private static function query(string $url): array
	{
		parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
		return $query;
	}

	/** True if $id is valid for the provider and safe as a path segment. */
	public static function validId(array $definition, string $id): bool
	{
		return preg_match(self::SAFE_ID, $id) === 1 && preg_match($definition['id'], $id) === 1;
	}
}
