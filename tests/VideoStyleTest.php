<?php

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Kirby 6 dropped the `style` attribute of the (video:) tag, which let content
 * set CSS on the page. Shoji's override of the tag never prints it either, with
 * Shoji on or off and for every kind of address. It still reads `style:` as an
 * attribute, so a tag that carries it shows the same video.
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
final class VideoStyleTest extends TestCase
{
	public function testStyleIsDroppedAndTheVideoStays(): void
	{
		$tmp = sys_get_temp_dir() . '/shoji-style-' . bin2hex(random_bytes(6));
		foreach (['public', 'content/home', 'site/plugins', 'storage'] as $dir) {
			mkdir($tmp . '/' . $dir, 0700, true);
		}
		file_put_contents($tmp . '/content/home/home.txt', "Title: Home\n");
		file_put_contents($tmp . '/content/home/film.mp4', 'video');
		symlink(dirname(__DIR__), $tmp . '/site/plugins/shoji');
		$addresses = ['https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://vimeo.com/76979871', 'https://media.example/film.mp4', 'film.mp4'];

		try {
			foreach ([false, true] as $active) {
				$app = new App([
					'roots' => ['index' => $tmp . '/public', 'content' => $tmp . '/content', 'site' => $tmp . '/site', 'storage' => $tmp . '/storage'],
					'options' => ['url' => 'https://site.example', 'shoji.active' => $active],
				]);
				// Without the script tag: Shoji prints it once per request, in the first render only.
				$render = fn (string $tag): string => preg_replace('~<script\b[^>]*></script>~', '', $app->kirbytags($tag, ['parent' => $app->page('home')]));
				foreach ($addresses as $address) {
					foreach (['', ' caption: Film'] as $caption) {
						$expected = $render('(video: ' . $address . $caption . ')');
						// Directly after the address, and after another attribute.
						$html = $render('(video: ' . $address . ' style: position:fixed' . $caption . ')');
						$last = $render('(video: ' . $address . $caption . ' style: position:fixed)');
						$label = ($active ? 'on' : 'off') . ': ' . $address . $caption;
						$this->assertMatchesRegularExpression('~<(?:iframe|video)\b|shoji-(?:button|link)~', $expected, $label . ', without style');
						$this->assertSame($expected, $html, $label . ', style after the address');
						$this->assertSame($expected, $last, $label . ', style at the end');
						$this->assertDoesNotMatchRegularExpression('~\sstyle\s*=~i', $html, $label);
					}
				}
			}
		} finally {
			Dir::remove($tmp);
		}
	}
}
