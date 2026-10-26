<?php

namespace Sayamaapps\Shoji;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\Helpers;
use Sudare\Sudare;
use WeakMap;

/**
 * Reads `sayamaapps.shoji.*` options, with the 1.x names `shoji.*` as fallback.
 *
 * `null` counts as "not set", like Kirby's own option() (A::get() checks
 * isset() as well), so `'shoji.privacy' => null` falls back to the default.
 *
 * Both spellings work, flat (`'shoji.active' => true`) and nested
 * (`'shoji' => ['active' => true]`), because Kirby's option() finds both.
 */
final class Options
{
	/** Option prefix since 1.4 (OPT-1). */
	public const PREFIX = 'sayamaapps.shoji.';

	/** Option prefix of 1.x, read as a fallback until the next major version. */
	public const LEGACY_PREFIX = 'shoji.';

	/** Pages tried, in this order, for the privacy link when `shoji.privacy` is not set. */
	public const PRIVACY_PAGES = ['datenschutz', 'datenschutzerklaerung', 'privacy', 'privacy-policy'];

	/** Master switch. Only `true` switches on. */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active', false) === true;
	}

	/** storage/shoji: secret and preview image cache, outside the web root. */
	public static function directory(App $kirby): string
	{
		return rtrim($kirby->root('storage') ?? ($kirby->root('site') . '/storage'), '/') . '/shoji';
	}

	/**
	 * Reads `sayamaapps.shoji.<key>`. The 1.x spelling `shoji.<key>` still works as a
	 * fallback and is reported as a deprecation (README, Deprecations).
	 */
	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		$value = $kirby->option(self::PREFIX . $key);
		if ($value !== null) {
			return $value;
		}

		$legacy = $kirby->option(self::LEGACY_PREFIX . $key);
		if ($legacy !== null) {
			Helpers::deprecated(
				'Shoji: shoji.' . $key . ' is deprecated, use ' . self::PREFIX . $key,
				'option-prefix'
			);
		}

		return $legacy ?? $default;
	}

	/**
	 * Language of the visitor-facing text: `shoji.language`, otherwise the
	 * language of the current request (multi-language sites), the default
	 * language, the first two letters of Kirby's `locale` option, English.
	 * Only languages Shoji ships (I18n::LANGUAGES) are returned.
	 */
	public static function language(App $kirby): string
	{
		$candidates = [self::get($kirby, 'language')];
		try {
			$candidates[] = $kirby->language()?->code();
			$candidates[] = $kirby->defaultLanguage()?->code();
		} catch (\Throwable) {
			// No languages configured.
		}
		$locale = $kirby->option('locale');
		if (is_array($locale)) {
			$locale = $locale[LC_ALL] ?? reset($locale);
		}
		$candidates[] = is_string($locale) ? substr($locale, 0, 2) : null;

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && in_array(strtolower($candidate), I18n::LANGUAGES, true)) {
				return strtolower($candidate);
			}
		}
		return 'en';
	}

	/**
	 * `shoji.open`, checked (Providers::open): provider name => true for
	 * content that loads with the page, false or missing for content behind
	 * the click. Read once per Kirby instance, so a typo is logged once.
	 *
	 * @return array<string, bool>
	 */
	public static function open(App $kirby): array
	{
		static $cache = null;
		$cache ??= new WeakMap();
		return $cache[$kirby] ??= Providers::open(self::get($kirby, 'open'), Embed::providers($kirby));
	}

	/**
	 * Whether a page loads this provider's content right away: Shoji is on
	 * and `shoji.open` switches the provider on. A single call's `open`
	 * can still differ. Behind `$site->shojiOpen()`.
	 */
	public static function opens(App $kirby, string $provider): bool
	{
		return self::active($kirby) && (self::open($kirby)[$provider] ?? false);
	}

	/**
	 * Address of the privacy policy for the link in the note, or null (no
	 * link). `shoji.privacy` may be a URL, a page id or a closure returning
	 * either; `false` switches the link off. Without the option the first
	 * existing page of PRIVACY_PAGES is used.
	 */
	public static function privacyUrl(App $kirby): string|null
	{
		$option = self::get($kirby, 'privacy');
		if ($option instanceof Closure) {
			$option = $option();
		}
		if ($option === false) {
			return null;
		}
		if (is_string($option) && trim($option) !== '') {
			$option = trim($option);
			if (preg_match('~^(https?:)?//|^/~i', $option)) {
				return $option;
			}
			return $kirby->page($option)?->url();
		}
		foreach (self::PRIVACY_PAGES as $id) {
			if ($page = $kirby->page($id)) {
				return $page->url();
			}
		}
		return null;
	}

	/**
	 * The secret that signs Shoji's own addresses. `shoji.secret` (for
	 * several servers behind one domain), otherwise a random value created
	 * once in storage/shoji/secret.
	 */
	public static function secret(App $kirby): string
	{
		$option = self::get($kirby, 'secret');
		if (is_string($option) && strlen($option) >= 16) {
			return $option;
		}
		return Signer::storedSecret(self::directory($kirby));
	}

	/**
	 * Cache-Control of a preview image answer (real image and blank SVG):
	 * `private, no-store` while the request is behind Sudare's preview
	 * protection (Sudare's contract for other packages: `class_exists(\Sudare\Sudare::class)
	 * && \Sudare\Sudare::active()`, no Composer dependency), so a shared cache in
	 * front of a protected preview stores nothing; otherwise `public, max-age=N`.
	 *
	 * @psalm-suppress UndefinedClass Sudare is optional; the class_exists() guard decides.
	 */
	public static function thumbCacheControl(int $maxAge): string
	{
		if (class_exists(Sudare::class) && Sudare::active()) {
			return 'private, no-store';
		}
		return 'public, max-age=' . $maxAge;
	}
}
