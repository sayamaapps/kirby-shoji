<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Shoji\Options;

/**
 * Options are read as sayamaapps.shoji.<key>, with the 1.x spelling shoji.<key>
 * as fallback. The fallback is reported as a deprecation in debug mode.
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends TestCase
{
	public function testNewSpellingSwitchesShojiOn(): void
	{
		[$kirby, $tmp] = shojiKirby(['sayamaapps.shoji.active' => true]);
		try {
			$this->assertTrue(Options::active($kirby), 'sayamaapps.shoji.active is read');
		} finally {
			removeTree($tmp);
		}
	}

	public function testNewSpellingWinsOverOldSpelling(): void
	{
		[$kirby, $tmp] = shojiKirby(['sayamaapps.shoji.active' => false, 'shoji.active' => true]);
		try {
			$this->assertFalse(Options::active($kirby), 'sayamaapps.shoji.active wins when both are set');
		} finally {
			removeTree($tmp);
		}
	}

	public function testNotSetFallsBackToTheDefault(): void
	{
		[$kirby, $tmp] = shojiKirby();
		try {
			$this->assertFalse(Options::active($kirby), 'unset: default false');
			$this->assertSame('en', Options::language($kirby), 'unset: default language');
		} finally {
			removeTree($tmp);
		}
	}

	public function testOldSpellingStillWorksAndIsDeprecated(): void
	{
		$deprecations = [];
		set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);

		try {
			// Kirby reports deprecations in debug mode only.
			[$kirby, $tmp] = shojiKirby(['debug' => true, 'shoji.active' => true]);
			try {
				$this->assertTrue(Options::active($kirby), 'shoji.active is read as fallback');
			} finally {
				removeTree($tmp);
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame(['Shoji: shoji.active is deprecated, use sayamaapps.shoji.active'], $deprecations);
	}
}
