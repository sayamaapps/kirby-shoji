<?php

use Kirby\Cms\App;
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

	/** @return array{0:Thumbs,1:array{count:int},2:Embed,3:string} */
	private function failingThumbs(array $params = []): array
	{
		[$kirby, $tmp] = $this->site();
		$calls = new stdClass();
		$calls->count = 0;
		$thumbs = new Thumbs($tmp . '/thumbs', function (string $url) use ($calls): array {
			$calls->count++;
			return [404, ''];
		});
		return [$thumbs, $calls, Embed::make($kirby, 'vimeo', '76979871', $params), $tmp];
	}

	public function testAMissBlocksTheProviderForTheFullDay(): void
	{
		[$thumbs, $calls, $embed] = $this->failingThumbs();
		$start = 1_800_000_000;

		$this->assertNull($thumbs->get($embed, $start));
		$this->assertSame(1, $calls->count, 'the first request asks the provider');

		foreach ([3600, 43200, 86399] as $offset) {
			$this->assertNull($thumbs->get($embed, $start + $offset));
			$this->assertSame(1, $calls->count, "no new fetch $offset seconds after the miss");
		}

		$this->assertNull($thumbs->get($embed, $start + 86401));
		$this->assertSame(2, $calls->count, 'a day and a second after the miss the provider is asked again');
	}

	public function testAMissForOneVariantIsNotShortenedByAnotherVariant(): void
	{
		[$thumbs, $calls, $plain] = $this->failingThumbs();
		$withHash = Embed::make(App::instance(), 'vimeo', '76979871', ['h' => 'abcdef12']);
		$start = 1_800_000_000;

		$thumbs->get($plain, $start);
		$thumbs->get($withHash, $start + 3600);
		$this->assertSame(2, $calls->count, 'each variant is asked once');

		$thumbs->get($plain, $start + 7200);
		$thumbs->get($withHash, $start + 7200);
		$this->assertSame(2, $calls->count, 'the other variant did not wipe the miss, neither one asks again within a day');
	}

	public function testAFailedRefreshOfAnOldCopyKeepsItAndBlocksTheProviderFor30Days(): void
	{
		[$thumbs, $calls, $embed, $tmp] = $this->failingThumbs();
		$start = 1_800_000_000;
		$old = $tmp . '/thumbs/vimeo/76979871.jpg';
		mkdir(dirname($old), 0755, true);
		file_put_contents($old, "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000));
		touch($old, $start - Thumbs::MAX_AGE - 10);

		$this->assertSame($old, $thumbs->get($embed, $start)['file'] ?? null, 'the old copy is shown');
		$this->assertSame(1, $calls->count, 'the refresh was tried once');

		clearstatcache(); // the next request is a new process in production
		$this->assertSame($old, $thumbs->get($embed, $start + Thumbs::MAX_AGE - 1)['file'] ?? null);
		$this->assertSame(1, $calls->count, 'no new refresh within 30 days of the failed one');
	}
}
