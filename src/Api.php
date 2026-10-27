<?php

namespace Sayamaapps\Shoji;

use Kirby\Cms\App;
use Kirby\Cms\Html;
use Kirby\Http\Response;
use Kirby\Text\KirbyTag;
use WeakMap;

/**
 * The entry points of the plugin: what the snippets, the routes and the
 * (video:) tag call. legacy.php keeps the 1.x helper functions as wrappers
 * around these methods.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Api
{
	/** Kirby's own tag definitions, read once from the core. */
	private static array|null $coreTags = null;

	/** Kirby instances that already printed the script tag (one tag per page). */
	private static WeakMap|null $printed = null;

	/**
	 * Kirby's own tag definition (html, attr, …) for a tag name, from the core.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function coreTag(App $kirby, string $name): array|null
	{
		return self::coreTags($kirby)[$name] ?? null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function coreTags(App $kirby): array
	{
		self::$coreTags ??= require $kirby->root('kirby') . '/config/tags.php';
		return self::$coreTags;
	}

	/**
	 * The embed for a pasted address or a provider and id; null if nothing
	 * matches or the id is invalid.
	 */
	public static function embed(
		App $kirby,
		string $urlOrProvider,
		string|null $id = null,
		array $params = [],
		string|null $title = null
	): Embed|null {
		return $id === null
			? Embed::fromUrl($kirby, $urlOrProvider, $params, $title)
			: Embed::make($kirby, $urlOrProvider, $id, $params, $title);
	}

	/**
	 * Sets the language and the `shoji.texts` overrides for this request.
	 * Snippets call it before they print any text.
	 */
	public static function i18n(App $kirby): void
	{
		I18n::setLanguage(Options::language($kirby));
		I18n::setOverrides(Options::get($kirby, 'texts'));
	}

	/** Kind 'html': the site's own markup, rendered now (after the click), never cached: parameters may differ on the next click. */
	private static function markup(Embed $embed): Response
	{
		$html = $embed->html();
		if ($html === null) {
			if ($embed->snippet() !== null) {
				error_log('Shoji: the snippet ' . $embed->snippet() . ' of provider ' . $embed->provider . ' is missing or empty');
			}
			return self::notFound();
		}
		return new Response($html, 'text/html', 200, [
			'Cache-Control'          => 'no-store',
			'X-Robots-Tag'           => 'noindex, nofollow',
			'X-Content-Type-Options' => 'nosniff',
		]);
	}

	private static function notFound(): Response
	{
		return new Response('Not found', 'text/plain', 404, ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);
	}

	/**
	 * An option, with the given default when it is not set.
	 */
	public static function option(App $kirby, string $key, mixed $default = null): mixed
	{
		return Options::get($kirby, $key, $default);
	}

	/** The preview image from the cache or the provider; a blank image in the right proportions if there is none. */
	private static function preview(App $kirby, Embed $embed): Response
	{
		$image = $embed->hasThumbnail() ? (new Thumbs(Options::directory($kirby) . '/thumbs', Http::get(...)))->get($embed) : null;
		if ($image === null) {
			[$width, $height] = $embed->size() ?? [16, 9];
			return new Response(
				'<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"/>',
				'image/svg+xml',
				200,
				['Cache-Control' => Options::thumbCacheControl(3600), 'X-Content-Type-Options' => 'nosniff']
			);
		}
		return new Response((string)file_get_contents($image['file']), $image['type'], 200, [
			'Cache-Control'          => Options::thumbCacheControl(Thumbs::MAX_AGE),
			'X-Content-Type-Options' => 'nosniff',
		]);
	}

	/** Kinds frame, go, script and style: a redirect to the provider's address, no-store. */
	private static function redirect(Embed $embed, string $kind, int $index): Response
	{
		$target = $embed->target($kind, $index);
		if ($target === null) {
			return self::notFound();
		}
		// no-store: parameters (an affiliate id, say) may differ on the next click.
		// No Referrer-Policy here: YouTube refuses to play without a referrer.
		return new Response('', null, 302, [
			'Location'      => $target,
			'Cache-Control' => 'no-store',
			'X-Robots-Tag'  => 'noindex, nofollow',
		]);
	}

	/**
	 * The response of /shoji/<kind>/<provider>/<id>: the page's markup, a
	 * preview image, or a redirect to the provider. Null-free: a Response for
	 * every request the module answers, false while the module is off.
	 */
	public static function route(App $kirby, string $kind, string $provider, string $id): Response|false
	{
		if (!Options::active($kirby)) {
			return false; // the module does not even reveal itself: Kirby's 404
		}

		$signature = get('s');
		$encoded = in_array($kind, ['frame', 'go', 'html', 'thumb'], true) ? get('p', '') : '';
		// The second and later script or stylesheet: their index is signed instead of parameters.
		$index = in_array($kind, ['script', 'style'], true) ? get('n', '0') : '0';
		if (!is_string($signature) || !is_string($encoded) || !is_string($index) || !preg_match('/^(?:0|[1-9]\d?)\z/', $index)) {
			return self::notFound();
		}
		$embed = Embed::make($kirby, $provider, $id, Embed::decodeParams($encoded));
		$signed = $index !== '0' ? Embed::encodeIndex((int)$index) : $encoded;
		if ($embed === null || !Embed::signer($kirby)->verify($signature, $kind, $provider, $id, $signed)) {
			return self::notFound();
		}

		return match ($kind) {
			'html'  => self::markup($embed),
			'thumb' => self::preview($kirby, $embed),
			default => self::redirect($embed, $kind, (int)$index),
		};
	}

	/**
	 * The <script> tag for shoji.js, once per page: the first placeholder prints
	 * it, later ones print nothing. External and deferred, so a Content Security
	 * Policy needs no 'unsafe-inline'. `shoji.script` set to false turns it off
	 * for sites that bundle the file themselves.
	 */
	public static function scriptTag(App $kirby): string
	{
		self::$printed ??= new WeakMap();
		if (isset(self::$printed[$kirby]) || Options::get($kirby, 'script', true) === false || ($url = self::scriptUrl($kirby)) === null) {
			return '';
		}
		self::$printed[$kirby] = true;
		return '<script src="' . esc($url) . '" defer></script>';
	}

	/**
	 * The address of assets/shoji.js (Kirby serves plugin assets with a version hash).
	 */
	public static function scriptUrl(App $kirby): string|null
	{
		return $kirby->plugin('sayamaapps/shoji')?->asset('shoji.js')?->url();
	}

	/**
	 * The HTML of the (video:) tag: Shoji's output from video(), or Kirby's own
	 * tag for everything Shoji does not take over.
	 */
	public static function tag(KirbyTag $tag): string
	{
		$content = self::video($tag->kirby(), (string)$tag->value);
		if ($content === null) {
			return self::coreTags($tag->kirby())['video']['html']($tag);
		}
		return Html::figure(
			[$content],
			$tag->attr('caption'),
			['class' => $tag->attr('class') ?? 'video', 'style' => $tag->attr('style')]
		);
	}

	/**
	 * What the (video:) tag and the video block print for an address while
	 * Shoji is on: the placeholder; for an address on a protected provider's
	 * host that Shoji cannot embed, the fallback (a note and a plain link),
	 * never Kirby's own output, which would load the provider with the page;
	 * null for everything else (media files, other hosts), which stays Kirby's.
	 */
	public static function video(App $kirby, string $url): string|null
	{
		if (!Options::active($kirby)) {
			return null;
		}
		if ($embed = Embed::fromUrl($kirby, $url)) {
			return (string)$kirby->snippet('shoji/embed', ['embed' => $embed], true);
		}
		return Providers::isProtectedHost($url)
			? (string)$kirby->snippet('shoji/fallback', ['url' => trim($url)], true)
			: null;
	}
}
