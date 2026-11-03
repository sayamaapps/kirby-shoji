<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Shoji\Http;

/**
 * The stream wrapper path of Http, used when curl is not available: the status
 * line of the answer must reach the caller, also on PHP before 8.4, where
 * PHP leaves the headers in a variable of the scope that made the request.
 * The request is a real one, to a local server (tests/fixtures/http-router.php
 * under `php -S`). Http::$fake is not used.
 *
 * Http::get() takes the stream path only without curl_init() and only for
 * https addresses, and a local test server has no certificate that PHP trusts.
 * So the test calls the private method stream() directly, the code Http::get()
 * runs without curl, with an http address.
 */
#[CoversClass(Http::class)]
final class HttpStreamTest extends TestCase
{
	private static string $base = '';

	/** @var resource|null */
	private static $server = null;

	public static function setUpBeforeClass(): void
	{
		$probe = stream_socket_server('tcp://127.0.0.1:0');
		self::assertIsResource($probe, 'a free port');
		$port = (int)substr((string)strrchr((string)stream_socket_get_name($probe, false), ':'), 1);
		fclose($probe);

		$null = ['file', '/dev/null', 'w'];
		$server = proc_open(
			[PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/fixtures/http-router.php'],
			[1 => $null, 2 => $null],
			$pipes,
			__DIR__ . '/fixtures'
		);
		self::assertIsResource($server, 'the local server starts');
		self::$server = $server;
		self::$base = 'http://127.0.0.1:' . $port;

		$deadline = microtime(true) + 10;
		do {
			$connection = @fsockopen('127.0.0.1', $port, $code, $message, 0.2);
			if ($connection !== false) {
				fclose($connection);
				return;
			}
			usleep(20_000);
		} while (microtime(true) < $deadline);
		self::fail('the local server does not answer');
	}

	/** @return array{0:int,1:string} what the stream path makes of the answer to $path */
	private function stream(string $path): array
	{
		$this->assertNull(Http::$fake, 'the network is not replaced');
		/** @var array{0:int,1:string} */
		return (new ReflectionMethod(Http::class, 'stream'))->invoke(null, self::$base . $path);
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$server !== null) {
			proc_terminate(self::$server);
			proc_close(self::$server);
			self::$server = null;
		}
	}

	public function testABodyUpToTheLimitIsKeptAndOneByteMoreIsDropped(): void
	{
		$this->assertSame([200, str_repeat('x', Http::MAX_BYTES)], $this->stream('/limit'), 'exactly the limit is kept');
		$this->assertSame([0, ''], $this->stream('/toobig'), 'one byte more gives no answer');
	}

	public function testAnEmptyAnswerKeepsItsStatus(): void
	{
		$this->assertSame([200, ''], $this->stream('/nobody'), 'status 200 with no body');
	}

	public function testAnErrorStatusIsReportedWithItsBody(): void
	{
		$this->assertSame([404, 'not here'], $this->stream('/missing'), 'status 404, not 0');
	}

	public function testASuccessfulAnswerKeepsItsStatusAndBody(): void
	{
		$this->assertSame([200, "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000)], $this->stream('/image'), 'status 200 and the bytes');
	}

	public function testNothingListeningGivesNoAnswer(): void
	{
		$probe = stream_socket_server('tcp://127.0.0.1:0');
		$this->assertIsResource($probe);
		$port = (int)substr((string)strrchr((string)stream_socket_get_name($probe, false), ':'), 1);
		fclose($probe);

		$this->assertSame([0, ''], (new ReflectionMethod(Http::class, 'stream'))->invoke(null, 'http://127.0.0.1:' . $port . '/image'), 'status 0 and no body');
	}

	public function testTheStatusOfTheLastRequestCountsAfterARedirect(): void
	{
		$this->assertSame([200, "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000)], $this->stream('/redirect'), 'the 200 after the 302');
	}
}
