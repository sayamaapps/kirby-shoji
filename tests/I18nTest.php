<?php

/**
 * Translations without Kirby: every language has the same keys and
 * placeholders as English; the texts are registered as Kirby plugin
 * translations (`sayamaapps.shoji.<key>` and `shoji.<key>`); `shoji.texts`
 * replaces single strings, per language or for all. The lookup through Kirby
 * is covered in KirbyTest, which boots the app with the plugin.
 *   php tests/I18nTest.php
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Shoji\I18n;

#[CoversClass(I18n::class)]
final class I18nTest extends TestCase
{
	/** The table of translations/<language>.php. */
	private function table(string $language): array
	{
		return require dirname(__DIR__) . '/translations/' . $language . '.php';
	}

	/** Placeholder names of a text, sorted. */
	private function placeholders(string $text): array
	{
		preg_match_all('/\{(\w+)\}/', $text, $m);
		$names = array_unique($m[1]);
		sort($names);
		return $names;
	}

	public function testEveryLanguageHasTheKeysAndPlaceholdersOfEnglish(): void
	{
		$en = $this->table('en');
		expect(true, count($en) >= 10, 'English table loaded');
		foreach (I18n::LANGUAGES as $language) {
			$table = $this->table($language);
			expect([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
			expect([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
			foreach ($en as $key => $text) {
				expect(true, is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
				expect($this->placeholders($text), $this->placeholders($table[$key]), "$language.$key: same placeholders as English");
			}
		}
	}

	public function testTheTextsAreRegisteredUnderTheNewAndTheOldKey(): void
	{
		$translations = I18n::translations();
		foreach (I18n::LANGUAGES as $language) {
			expect($this->table($language)['privacy'], $translations[$language]['sayamaapps.shoji.privacy'] ?? null, "$language: sayamaapps.shoji.privacy");
			expect($this->table($language)['privacy'], $translations[$language]['shoji.privacy'] ?? null, "$language: shoji.privacy (1.x key)");
		}
	}

	public function testUnknownLanguageFallsBackToEnglish(): void
	{
		I18n::setLanguage('fr');
		expect('en', I18n::language(), 'unknown language falls back to English');
		I18n::setLanguage('en');
	}

	public function testOverrides(): void
	{
		// shoji.texts: flat keys for every language, the language's own table wins
		I18n::setLanguage('de');
		I18n::setOverrides(['privacy' => 'Datenschutz', 'de' => ['note.video' => 'Erst nach Deinem Klick lädt {name}.'], 'en' => ['privacy' => 'Privacy']]);
		expect('Datenschutz', I18n::text('privacy'), 'flat override');
		expect('Erst nach Deinem Klick lädt YouTube.', I18n::text('note.video', ['name' => 'YouTube', 'company' => 'Google']), 'per-language override with placeholders');
		I18n::setLanguage('en');
		I18n::setOverrides(['privacy' => 'Datenschutz', 'en' => ['privacy' => 'Privacy']]);
		expect('Privacy', I18n::text('privacy'), 'language table beats flat key');
		I18n::setOverrides(['privacy' => ['not a string']]);
		holds(is_string(I18n::text('privacy')) && I18n::text('privacy') !== '', 'non-string override ignored');
		I18n::setOverrides(null);
	}
}
