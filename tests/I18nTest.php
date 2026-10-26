<?php

/**
 * Translations: every language has the same keys and placeholders as
 * English; `shoji.texts` replaces single strings, per language or for all.
 *   php tests/I18nTest.php
 */

use Shoji\I18n;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(I18n::class)]
final class I18nTest extends TestCase
{
    public function testSuite(): void
    {


    $placeholders = function (string $text): array {
        preg_match_all('/\{(\w+)\}/', $text, $m);
        $names = array_unique($m[1]);
        sort($names);
        return $names;
    };


    $en = I18n::table('en');
    expect(true, count($en) >= 10, 'English table loaded');
    foreach (I18n::LANGUAGES as $language) {
        $table = I18n::table($language);
        expect([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
        expect([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
        foreach ($en as $key => $text) {
            expect(true, is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
            expect($placeholders($text), $placeholders($table[$key]), "$language.$key: same placeholders as English");
        }
    }
    expect([], I18n::table('fr'), 'unknown language: no table');

    I18n::setLanguage('fr');
    expect('en', I18n::language(), 'unknown language falls back to English');
    I18n::setLanguage('de');
    expect('Datenschutzerklärung', I18n::t('privacy'), 'German string');
    expect('Video abspielen: Trailer', I18n::t('button.videoTitle', ['title' => 'Trailer']), 'placeholder filled');
    expect('nope.key', I18n::t('nope.key'), 'unknown key comes back as the key');

    // shoji.texts: flat keys for every language, the language's own table wins
    I18n::setOverrides(['privacy' => 'Datenschutz', 'de' => ['note.video' => 'Erst nach Deinem Klick lädt {name}.'], 'en' => ['privacy' => 'Privacy']]);
    expect('Datenschutz', I18n::t('privacy'), 'flat override');
    expect('Erst nach Deinem Klick lädt YouTube.', I18n::t('note.video', ['name' => 'YouTube', 'company' => 'Google']), 'per-language override with placeholders');
    I18n::setLanguage('en');
    I18n::setOverrides(['privacy' => 'Datenschutz', 'en' => ['privacy' => 'Privacy']]);
    expect('Privacy', I18n::t('privacy'), 'language table beats flat key');
    I18n::setOverrides(null);
    expect('Privacy policy', I18n::t('privacy'), 'no overrides: the shipped text');
    I18n::setOverrides(['privacy' => ['not a string']]);
    expect('Privacy policy', I18n::t('privacy'), 'non-string override ignored');
    }
}
