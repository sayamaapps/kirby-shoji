<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Shoji\Http;
use Sayamaapps\Shoji\Signer;

/**
 * The 1.x names that stay until the next major version: the test hook of Http
 * still replaces the network, and the signer keeps its 1.x exception class.
 * Neither logs anything.
 */
#[CoversClass(Http::class)]
#[CoversClass(Signer::class)]
final class CompatibilityTest extends TestCase
{
	public function testTheOneXTestHookStillReplacesTheNetwork(): void
	{
		$asked = [];
		Http::$fake = function (string $url) use (&$asked): array {
			$asked[] = $url;
			return [200, 'bytes'];
		};
		try {
			$this->assertSame([200, 'bytes'], Http::get('https://example.com/preview.jpg'), 'the fake answers');
		} finally {
			Http::$fake = null;
		}
		$this->assertSame(['https://example.com/preview.jpg'], $asked, 'the fake was asked for the address');
	}

	public function testAShortSecretIsRefusedWithTheOneXExceptionClass(): void
	{
		$this->expectException(RuntimeException::class);
		new Signer('short');
	}
}
