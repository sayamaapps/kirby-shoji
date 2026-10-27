<?php

use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Shoji\Embed;
use Sayamaapps\Shoji\Thumbs;

/**
 * Preview images: the server fetches the provider's candidates in order, keeps
 * the first real image, and remembers a failed first fetch for a day. The
 * network is replaced by the closure given to Thumbs.
 */
#[CoversClass(Thumbs::class)]
final class ThumbsTest extends ShojiTestCase
{
	public function testAFailedFirstFetchIsRememberedForADay(): void
	{
		$asked = [];
		[$kirby, $tmp] = $this->site();
		$embed = Embed::make($kirby, 'vimeo', '76979871');
		$thumbs = new Thumbs($tmp . '/thumbs', function (string $url) use (&$asked): array {
			$asked[] = $url;
			return [200, '<html>not an image</html>' . str_repeat(' ', 2000)];
		});

		$this->assertNull($thumbs->get($embed), 'an answer that is no image gives no preview image');
		$this->assertFileExists($tmp . '/thumbs/vimeo/76979871.miss', 'the miss is remembered');
		$this->assertCount(1, $asked, 'the oEmbed answer was asked once');

		$asked = [];
		$this->assertNull($thumbs->get($embed), 'still no preview image');
		$this->assertSame([], $asked, 'no new fetch within a day after a miss');
	}

	public function testCandidatesAreFetchedInOrderAndTheImageIsCached(): void
	{
		$jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000);
		$asked = [];
		[$kirby, $tmp] = $this->site();
		$embed = Embed::make($kirby, 'youtube', 'dQw4w9WgXcQ');
		$thumbs = new Thumbs($tmp . '/thumbs', function (string $url) use (&$asked, $jpeg): array {
			$asked[] = $url;
			return str_contains($url, 'maxresdefault') ? [404, str_repeat('p', 1100)] : [200, $jpeg];
		});

		$image = $thumbs->get($embed);
		$this->assertSame('image/jpeg', $image['type'] ?? null, 'the JPEG is taken');
		$this->assertSame($jpeg, file_get_contents($image['file']), 'the fetched bytes are cached');
		$this->assertSame(
			['https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'],
			$asked,
			'maxres missing, hq taken'
		);

		$asked = [];
		$thumbs->get($embed);
		$this->assertSame([], $asked, 'the second request is served from the cache');
	}
}
