<?php

namespace Shoji;

use Closure;
use Kirby\Cms\App;

/**
 * Reads `shoji.*` options.
 *
 * `null` counts as "not set", like Kirby's own option() (A::get() checks
 * isset() as well), so `'shoji.privacy' => null` falls back to the default.
 *
 * Both spellings work, flat (`'shoji.active' => true`) and nested
 * (`'shoji' => ['active' => true]`), because Kirby's option() finds both.
 */
final class Options
{
    public const PREFIX = 'shoji.';

    /** Pages tried, in this order, for the privacy link when `shoji.privacy` is not set. */
    public const PRIVACY_PAGES = ['datenschutz', 'datenschutzerklaerung', 'privacy', 'privacy-policy'];

    public static function get(App $kirby, string $key, mixed $default = null): mixed
    {
        return $kirby->option(self::PREFIX . $key) ?? $default;
    }

    /** Master switch. Only `true` switches on. */
    public static function active(App $kirby): bool
    {
        return self::get($kirby, 'active', false) === true;
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
     * Address of the privacy policy for the link in the note, or null (no
     * link). `shoji.privacy` may be a URL, a page id or a closure returning
     * either; `false` switches the link off. Without the option the first
     * existing page of PRIVACY_PAGES is used.
     */
    public static function privacyUrl(App $kirby): ?string
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

    /** storage/shoji: secret and preview image cache, outside the web root. */
    public static function directory(App $kirby): string
    {
        return rtrim($kirby->root('storage') ?? ($kirby->root('site') . '/storage'), '/') . '/shoji';
    }
}
