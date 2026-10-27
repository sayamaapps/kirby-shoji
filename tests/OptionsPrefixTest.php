<?php

use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Shoji\Options;

/**
 * Options are read as sayamaapps.shoji.<key>, with the 1.x spelling shoji.<key>
 * as fallback. The fallback is reported as a deprecation in debug mode.
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends ShojiTestCase
{
	public function testNewSpellingSwitchesShojiOn(): void
	{
		[$kirby, $tmp] = $this->site(['sayamaapps.shoji.active' => true]);
		$this->assertTrue(Options::active($kirby), 'sayamaapps.shoji.active is read');
	}

	public function testNewSpellingWinsOverOldSpelling(): void
	{
		[$kirby, $tmp] = $this->site(['sayamaapps.shoji.active' => false, 'shoji.active' => true]);
		$this->assertFalse(Options::active($kirby), 'sayamaapps.shoji.active wins when both are set');
	}

	public function testNotSetFallsBackToTheDefault(): void
	{
		[$kirby, $tmp] = $this->site();
		$this->assertFalse(Options::active($kirby), 'unset: default false');
		$this->assertSame('en', Options::language($kirby), 'unset: default language');
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
			[$kirby, $tmp] = $this->site(['debug' => true, 'shoji.active' => true]);
			$this->assertTrue(Options::active($kirby), 'shoji.active is read as fallback');
		} finally {
			restore_error_handler();
		}

		$this->assertSame(['Shoji: shoji.active is deprecated, use sayamaapps.shoji.active'], $deprecations);
	}
}
