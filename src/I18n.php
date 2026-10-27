<?php

namespace Sayamaapps\Shoji;

use Kirby\Toolkit\I18n as KirbyI18n;

/**
 * Visitor-facing strings. Kirby's translation lookup does the work: the texts
 * are registered as plugin translations (config/translations.php) under
 * `sayamaapps.shoji.<key>`, and as `shoji.<key>` for sites that already use it.
 *
 * `shoji.texts` replaces single strings, per language or for all. Their keys
 * are the short keys of translations/<language>.php:
 *
 *   'sayamaapps.shoji.texts' => ['de' => ['note.video' => 'Das Video lädt erst nach Deinem Klick …']]
 *   'sayamaapps.shoji.texts' => ['privacy' => 'Datenschutz']
 *
 * The language is set once per request (Api::i18n(), Options::language()) and
 * defaults to English. Missing keys fall back to English, then to the key.
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class I18n
{
	/** Languages Shoji ships. English is the default and the fallback. */
	public const LANGUAGES = ['en', 'de'];

	/** Prefix of the translation keys registered in Kirby. */
	public const PREFIX = 'sayamaapps.shoji.';

	private static string $language = 'en';

	/** @var array<string,string> */
	private static array $overrides = [];

	/**
	 * The texts as Kirby plugin translations, per language: `sayamaapps.shoji.<key>`
	 * and the 1.x key `shoji.<key>`.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function translations(): array
	{
		$translations = [];
		foreach (self::LANGUAGES as $language) {
			foreach (require dirname(__DIR__) . '/translations/' . $language . '.php' as $key => $text) {
				$translations[$language][self::PREFIX . $key] = $text;
				$translations[$language]['shoji.' . $key] = $text;
			}
		}
		return $translations;
	}

	/**
	 * The language of the visitor-facing text for this request.
	 */
	public static function language(): string
	{
		return self::$language;
	}

	/**
	 * Sets the language of the visitor-facing text; an unknown language means English.
	 */
	public static function setLanguage(string $language): void
	{
		self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
	}

	/** Strings from `shoji.texts` for the current language; flat keys apply to every language. */
	public static function setOverrides(mixed $texts): void
	{
		self::$overrides = [];
		if (!is_array($texts)) {
			return;
		}
		// Flat keys first (language tables are arrays and skipped), then the
		// current language's table, which wins.
		$perLanguage = is_array($texts[self::$language] ?? null) ? $texts[self::$language] : [];
		foreach ([$texts, $perLanguage] as $table) {
			foreach ($table as $key => $text) {
				if (is_string($key) && is_string($text)) {
					self::$overrides[$key] = $text;
				}
			}
		}
	}

	/**
	 * The text for a key of translations/<language>.php in the current language.
	 * `{name}` placeholders are replaced with the given values, unescaped.
	 */
	public static function text(string $key, array $values = []): string
	{
		$text = self::$overrides[$key] ?? self::lookup(self::$language, $key) ?? self::lookup('en', $key) ?? $key;
		if ($values === []) {
			return $text;
		}
		$pairs = [];
		foreach ($values as $name => $value) {
			$pairs['{' . $name . '}'] = (string)$value;
		}
		return strtr($text, $pairs);
	}

	/** The Kirby translation of a key in one language, or null. */
	private static function lookup(string $language, string $key): string|null
	{
		$text = KirbyI18n::translate(self::PREFIX . $key, null, $language);
		return is_string($text) ? $text : null;
	}
}
