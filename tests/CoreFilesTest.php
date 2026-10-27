<?php

/**
 * The two Kirby core files Shoji reads by path: the tag definitions (the
 * fallback of the (video:) tag) and the video block. A Kirby update that moves
 * or changes them must fail here, not on a live page.
 *   php tests/CoreFilesTest.php
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CoreFilesTest extends TestCase
{
	private function core(string $path): string
	{
		return dirname(__DIR__) . '/vendor/getkirby/cms/' . $path;
	}

	public function testTheTagDefinitionsHaveAVideoTagWithAnHtmlCallable(): void
	{
		$this->assertFileExists($this->core('config/tags.php'));
		$tags = require $this->core('config/tags.php');
		$this->assertIsArray($tags, 'config/tags.php returns the definitions');
		$this->assertArrayHasKey('video', $tags, 'the video tag exists');
		$this->assertIsCallable($tags['video']['html'] ?? null, 'the video tag has an html callable');
	}

	public function testTheVideoBlockFileExists(): void
	{
		$this->assertFileExists($this->core('config/blocks/video/video.php'));
	}
}
