<?php

namespace Sayamaapps\Shoji;

/**
 * Visitor-facing strings, Kirby-free.
 *
 * The tables live in translations/<language>.php and are also registered as
 * Kirby plugin translations under `shoji.<key>` (index.php). Shoji itself
 * reads its own tables: a missing Kirby translation must never silently
 * turn a German note English.
 *
 * `shoji.texts` replaces single strings, per language or for all:
 *
 *   'shoji.texts' => ['de' => ['note.video' => 'Das Video lädt erst nach Deinem Klick …']]
 *   'shoji.texts' => ['privacy' => 'Datenschutz']
 *
 * The language is set once per request (index.php, Options::language()) and
 * defaults to English. Missing keys fall back to English, then to the key.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class I18n
{
	/** Languages Shoji ships. English is the default and the fallback. */
	public const LANGUAGES = ['en', 'de'];

	private static string $language = 'en';

	/** @var array<string,string> */
	private static array $overrides = [];

	/** @var array<string, array<string,string>> */
	private static array $tables = [];

	/**
	 * The language of the visitor-facing text for this request.
	 */
	public static function language(): string
	{
		return self::$language;
	}

	/**
	 * The tables as Kirby plugin translations, `shoji.<key>` per language, so a
	 * site can use t('shoji.<key>') as well.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function pluginTranslations(): array
	{
		$translations = [];
		foreach (self::LANGUAGES as $language) {
			foreach (self::table($language) as $key => $text) {
				$translations[$language]['shoji.' . $key] = $text;
			}
		}
		return $translations;
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

	/** `{name}` placeholders are replaced with the given values, unescaped. */
	public static function t(string $key, array $values = []): string
	{
		$text = self::$overrides[$key] ?? self::table(self::$language)[$key] ?? self::table('en')[$key] ?? $key;
		if ($values === []) {
			return $text;
		}
		$pairs = [];
		foreach ($values as $name => $value) {
			$pairs['{' . $name . '}'] = (string)$value;
		}
		return strtr($text, $pairs);
	}

	/** @return array<string,string> */
	public static function table(string $language): array
	{
		if (!in_array($language, self::LANGUAGES, true)) {
			return [];
		}
		return self::$tables[$language] ??= require dirname(__DIR__) . '/translations/' . $language . '.php';
	}
}
