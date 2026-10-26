<?php

namespace Sayamaapps\Shoji;

/**
 * Preview images from the provider, fetched by the server and kept in
 * storage/shoji/thumbs/<provider>/. The visitor's browser only ever asks
 * this site.
 *
 * A fresh copy is served for 30 days, then fetched again; if that fails the
 * old copy stays. A failed first fetch is remembered for a day (a `.miss`
 * file that holds the address parameters it was made for), so a video without
 * a preview image does not make every page view ask the provider again. A
 * corrected parameter (a Vimeo hash) is asked for at once.
 */
final class Thumbs
{
	public const MAX_AGE = 86400 * 30;

	/** Smaller answers are placeholders (YouTube's grey 120×90 for a missing maxresdefault). */
	public const MIN_BYTES = 1000;
	public const MISS_AGE = 86400;
	public const TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

	public function __construct(private readonly string $directory)
	{
	}

	/** @return array{file:string,type:string}|null */
	private function cached(string $base): array|null
	{
		foreach (self::TYPES as $extension => $type) {
			if (is_file($base . '.' . $extension)) {
				return ['file' => $base . '.' . $extension, 'type' => $type];
			}
		}
		return null;
	}

	/** @return array{0:string,1:string}|null bytes and extension of the first usable candidate */
	private function fetch(Embed $embed): array|null
	{
		$fetch = static fn (string $url): array => Http::get($url);
		foreach ($embed->thumbnailCandidates($fetch) as $url) {
			[$status, $bytes] = Http::get($url);
			if ($status !== 200 || strlen($bytes) < self::MIN_BYTES) {
				continue;
			}
			if (($extension = self::sniff($bytes)) !== null) {
				return [$bytes, $extension];
			}
		}
		return null;
	}

	/**
	 * The cached or freshly fetched image: ['file' => path, 'type' => mime],
	 * or null if there is none.
	 *
	 * @return array{file:string,type:string}|null
	 */
	public function get(Embed $embed, int|null $now = null): array|null
	{
		$now ??= time();
		$base = $this->directory . '/' . $embed->provider . '/' . $embed->id;
		$cached = $this->cached($base);
		// The miss belongs to the address parameters it was made with: a Vimeo hash that
		// was corrected asks the provider again, the same one does not.
		$missKey = Embed::encodeParams($embed->params);

		if ($cached !== null && $now - (int)filemtime($cached['file']) < self::MAX_AGE) {
			return $cached;
		}
		if ($cached === null && is_file($base . '.miss') && $now - (int)filemtime($base . '.miss') < self::MISS_AGE &&
			file_get_contents($base . '.miss') === $missKey) {
			return null;
		}

		$fresh = $this->fetch($embed);
		if ($fresh === null) {
			if ($cached !== null) {
				@touch($cached['file'], $now); // try again in 30 days, keep showing the old one
				return $cached;
			}
			$this->write($base . '.miss', $missKey, $now);
			return null;
		}

		[$bytes, $extension] = $fresh;
		foreach (array_keys(self::TYPES) as $other) {
			if ($other !== $extension) {
				@unlink($base . '.' . $other);
			}
		}
		@unlink($base . '.miss');
		if (!$this->write($base . '.' . $extension, $bytes, $now)) {
			error_log('Shoji: cannot write the preview image cache in ' . $this->directory);
			return null;
		}
		return ['file' => $base . '.' . $extension, 'type' => self::TYPES[$extension]];
	}

	/** Image type from the first bytes, never from the provider's header: jpg, png, webp or null. */
	public static function sniff(string $bytes): string|null
	{
		return match (true) {
			str_starts_with($bytes, "\xFF\xD8\xFF")                                            => 'jpg',
			str_starts_with($bytes, "\x89PNG\r\n\x1A\n")                                        => 'png',
			str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP'                  => 'webp',
			default                                                                             => null,
		};
	}

	private function write(string $file, string $bytes, int $time): bool
	{
		$directory = dirname($file);
		if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
			return false;
		}
		$tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (@file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes) || !@rename($tmp, $file)) {
			@unlink($tmp);
			return false;
		}
		@touch($file, $time);
		return true;
	}
}
