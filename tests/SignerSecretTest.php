<?php

use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Shoji\Signer;

/**
 * The stored secret on first use. Two first requests at once must end up with
 * the same secret, or the addresses the first one already rendered stop
 * verifying. The race is reproduced with two real processes: tests/fixtures/secret-worker.php
 * stops each of them after is_file() reported the secret missing, and the test
 * lets them continue one after the other.
 */
#[CoversClass(Signer::class)]
final class SignerSecretTest extends ShojiTestCase
{
	/** @var list<string> */
	private array $files = [];
	private string $logBefore = '';

	/** @var list<resource> */
	private array $processes = [];

	/**
	 * @return array{0: string, 1: string} an empty secret directory and an empty control directory
	 */
	private function directories(): array
	{
		$tmp = $this->track(sys_get_temp_dir() . '/shoji-secret-' . bin2hex(random_bytes(6)));
		mkdir($tmp . '/secrets', 0700, true);
		mkdir($tmp . '/control', 0700);
		return [$tmp . '/secrets', $tmp . '/control'];
	}

	/**
	 * The lock file with an exclusive lock on it, taken by this process.
	 *
	 * @return resource
	 */
	private function hold(string $file)
	{
		$handle = fopen($file, 'c');
		$this->assertIsResource($handle);
		$this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'the test takes the lock');
		return $handle;
	}

	/** Sorted names of the files in a directory. */
	private function listing(string $directory): array
	{
		$names = array_values(array_diff((array)scandir($directory), ['.', '..']));
		sort($names);
		return $names;
	}

	/** Sends error_log() to a file of its own and returns its path. */
	private function logTo(): string
	{
		$log = $this->tempFile('shoji-log');
		ini_set('error_log', $log);
		return $log;
	}

	/**
	 * What worker $name reported.
	 *
	 * @return array{secret: string, signature: string}
	 */
	private function returned(string $control, string $name): array
	{
		$this->waitFor($control . '/returned-' . $name);
		$report = json_decode((string)file_get_contents($control . '/returned-' . $name), true);
		$this->assertIsArray($report);
		/** @var array{secret: string, signature: string} $report */
		return $report;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->logBefore = (string)ini_get('error_log');
	}

	protected function tearDown(): void
	{
		foreach ($this->processes as $process) {
			proc_terminate($process);
			proc_close($process);
		}
		$this->processes = [];
		ini_set('error_log', $this->logBefore);
		foreach ($this->files as $file) {
			unlink($file);
		}
		$this->files = [];
		parent::tearDown();
	}

	/** A new file in the temporary directory; it is removed in tearDown. */
	private function tempFile(string $prefix): string
	{
		$file = (string)tempnam(sys_get_temp_dir(), $prefix);
		$this->files[] = $file;
		return $file;
	}

	public function testALockThatStaysTakenFailsClosed(): void
	{
		[$secrets] = $this->directories();
		$lock = $this->hold($secrets . '/secret.lock');
		$log = $this->logTo();

		$started = microtime(true);
		$first = Signer::storedSecret($secrets, 0.2);
		$second = Signer::storedSecret($secrets, 0.2);
		fclose($lock);

		$this->assertGreaterThanOrEqual(0.3, microtime(true) - $started, 'each call waits for the lock for the given time');
		$this->assertSame(64, strlen($first), 'still a full-length secret');
		$this->assertNotSame($first, $second, 'a new random secret per call, nothing derivable');
		$this->assertFileDoesNotExist($secrets . '/secret', 'nothing is stored without the lock');
		$this->assertStringContainsString('Shoji: cannot write ' . $secrets . '/secret (no lock on ' . $secrets . '/secret.lock)', (string)file_get_contents($log), 'the problem is logged');
	}

	public function testAnExistingSecretIsReadWhileTheLockIsTaken(): void
	{
		[$secrets] = $this->directories();
		file_put_contents($secrets . '/secret', str_repeat('e', 64));
		$lock = $this->hold($secrets . '/secret.lock');

		$this->assertSame(str_repeat('e', 64), Signer::storedSecret($secrets, 0.0), 'a stored secret needs no lock');
		fclose($lock);
	}

	public function testAnExistingSecretIsReadWithoutALockFile(): void
	{
		[$secrets] = $this->directories();
		$existing = str_repeat('d', 48);
		file_put_contents($secrets . '/secret', $existing . "\n");

		$this->assertSame($existing, Signer::storedSecret($secrets), 'the secret is returned as it is');
		$this->assertSame($existing . "\n", file_get_contents($secrets . '/secret'), 'the file is unchanged');
		$this->assertSame(['secret'], $this->listing($secrets), 'the fast path creates no lock file');
	}

	public function testAnUnwritableDirectoryFailsClosedAsBefore(): void
	{
		$blocker = $this->tempFile('shoji-blocker');
		$log = $this->logTo();

		$first = Signer::storedSecret($blocker . '/storage');
		$second = Signer::storedSecret($blocker . '/storage');

		$this->assertSame(64, strlen($first), 'still a full-length secret');
		$this->assertNotSame($first, $second, 'a new random secret per call, nothing derivable');
		$this->assertStringContainsString('Shoji: cannot write ' . $blocker . '/storage/secret', (string)file_get_contents($log), 'the problem is logged');
	}

	public function testASecondFirstRequestWaitsForTheLockAndTakesTheSecretItFinds(): void
	{
		[$secrets, $control] = $this->directories();
		$lock = $this->hold($secrets . '/secret.lock');

		$this->worker('b', $secrets, $control, false);
		usleep(300000);
		$this->assertFileDoesNotExist($control . '/returned-b', 'the worker waits while the lock is taken');

		// What the request holding the lock does: store the secret, then let go.
		$held = str_repeat('c', 64);
		file_put_contents($secrets . '/secret', $held);
		flock($lock, LOCK_UN);
		fclose($lock);

		$this->assertSame($held, $this->returned($control, 'b')['secret'], 'the worker takes the secret that appeared meanwhile');
		$this->assertSame($held, file_get_contents($secrets . '/secret'), 'and leaves the file alone');
	}

	public function testASecretThatIsTooShortIsReplaced(): void
	{
		[$secrets] = $this->directories();
		file_put_contents($secrets . '/secret', 'short');

		$secret = Signer::storedSecret($secrets);
		$this->assertSame(64, strlen($secret), 'a full-length secret');
		$this->assertSame($secret, file_get_contents($secrets . '/secret'), 'it replaced the short one');
	}

	public function testSequentialFirstUseCreatesOneSecret(): void
	{
		[$secrets] = $this->directories();

		$first = Signer::storedSecret($secrets);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first, '64 hex characters');
		$this->assertSame($first, Signer::storedSecret($secrets), 'the second call reads it back');
		$this->assertSame($first, file_get_contents($secrets . '/secret'), 'the file holds the secret');
		$this->assertSame('600', substr(sprintf('%o', fileperms($secrets . '/secret')), -3), 'secret file mode 0600');
		$this->assertSame([], glob($secrets . '/*.tmp'), 'no temporary file is left behind');
	}

	public function testTheDirectoryIsCreatedWhenItIsMissing(): void
	{
		[$secrets] = $this->directories();
		$directory = $secrets . '/shoji';

		$secret = Signer::storedSecret($directory);
		$this->assertSame($secret, file_get_contents($directory . '/secret'), 'the secret is stored');
		$this->assertSame('700', substr(sprintf('%o', fileperms($directory)), -3), 'directory mode 0700');
	}

	public function testTwoFirstRequestsThatBothSawNoSecretShareOne(): void
	{
		[$secrets, $control] = $this->directories();
		$this->worker('a', $secrets, $control, true);
		$this->worker('b', $secrets, $control, true);
		$this->waitFor($control . '/ready-a');
		$this->waitFor($control . '/ready-b');
		$this->assertFileDoesNotExist($secrets . '/secret', 'both workers saw no secret');

		touch($control . '/release-a');
		$a = $this->returned($control, 'a');
		touch($control . '/release-b');
		$b = $this->returned($control, 'b');

		$stored = trim((string)file_get_contents($secrets . '/secret'));
		$this->assertSame(64, strlen($stored), 'a full-length secret is stored');
		$this->assertSame($stored, $a['secret'], 'the first worker signed with the stored secret');
		$this->assertSame($stored, $b['secret'], 'the second worker uses the secret the first one stored');
		$this->assertTrue(
			(new Signer($stored))->verify($a['signature'], 'frame', 'youtube', 'aaaaaaaaaaa'),
			'an address the first worker issued still verifies after the second one finished',
		);
		$this->assertSame($a['signature'], $b['signature'], 'both workers signed the same address the same way');
		$this->assertSame(['secret', 'secret.lock'], $this->listing($secrets), 'no temporary file is left behind');
	}

	private function waitFor(string $file): void
	{
		$deadline = microtime(true) + 10;
		while (!is_file($file)) {
			$this->assertLessThan($deadline, microtime(true), 'waited for ' . basename($file) . ' in vain' . $this->workerOutput(dirname($file)));
			usleep(1000);
		}
	}

	private function worker(string $name, string $secrets, string $control, bool $pause): void
	{
		$process = proc_open(
			[PHP_BINARY, __DIR__ . '/fixtures/secret-worker.php', $name, $secrets, $control, $pause ? '1' : '0'],
			[0 => ['file', '/dev/null', 'r'], 1 => ['file', $control . '/stdout-' . $name, 'w'], 2 => ['file', $control . '/stderr-' . $name, 'w']],
			$pipes,
		);
		$this->assertIsResource($process, 'the worker starts');
		$this->processes[] = $process;
	}

	private function workerOutput(string $control): string
	{
		$output = '';
		foreach ((array)glob($control . '/std*') as $file) {
			$output .= "\n" . basename((string)$file) . ': ' . file_get_contents((string)$file);
		}
		return $output;
	}
}
