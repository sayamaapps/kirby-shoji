<?php

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Kirby 6 dropped the `style` attribute of the (video:) tag, which let content
 * set CSS on the page. Shoji's override of the tag does not have it either,
 * with Shoji on or off and for every kind of address.
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
final class VideoStyleTest extends TestCase
{
	public function testStyleInContentNeverBecomesAStyleAttribute(): void
	{
		$tmp = sys_get_temp_dir() . '/shoji-style-' . bin2hex(random_bytes(6));
		foreach (['public', 'content', 'site/plugins', 'storage'] as $dir) {
			mkdir($tmp . '/' . $dir, 0700, true);
		}
		symlink(dirname(__DIR__), $tmp . '/site/plugins/shoji');

		try {
			foreach ([false, true] as $active) {
				$app = new App([
					'roots' => ['index' => $tmp . '/public', 'content' => $tmp . '/content', 'site' => $tmp . '/site', 'storage' => $tmp . '/storage'],
					'options' => ['url' => 'https://site.example', 'shoji.active' => $active],
				]);
				foreach (['https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://media.example/film.mp4'] as $url) {
					$html = $app->kirbytags('(video: ' . $url . ' caption: Film style: position:fixed)');
					$label = ($active ? 'on' : 'off') . ': ' . $url;
					$this->assertStringContainsString('<figure', $html, $label);
					$this->assertDoesNotMatchRegularExpression('~\sstyle\s*=~i', $html, $label);
				}
			}
		} finally {
			Dir::remove($tmp);
		}
	}

	public function testTheVideoTagHasNoStyleAttribute(): void
	{
		$tags = require dirname(__DIR__) . '/config/tags.php';
		$this->assertNotContains('style', $tags['video']['attr']);
	}
}
