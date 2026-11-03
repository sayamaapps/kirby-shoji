<?php

namespace Sayamaapps\Shoji;

use RuntimeException;

/**
 * Signs Shoji's own addresses (/shoji/thumb|frame|script|go/…), so only
 * addresses this site rendered work. Without the signature the preview image
 * route would be an open proxy: anyone could make the server fetch preview
 * images for arbitrary ids from the provider.
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Signer
{
	/** 16 hex characters = 64 bits. Plenty for "did this site render the address". */
	public const LENGTH = 16;

	/** Pause between two tries for the lock, in microseconds. */
	private const LOCK_STEP = 10000;

	/** How long a first request waits for the lock on the secret, in seconds. Creating it takes microseconds. */
	public const LOCK_WAIT = 5.0;

	/**
	 * Creates a signer for a secret of at least 16 characters.
	 */
	public function __construct(private readonly string $secret)
	{
		if (strlen($secret) < 16) {
			throw new RuntimeException('Shoji: the secret is too short');
		}
	}

	/**
	 * Writes a new secret next to $file and moves it into place, so a reader
	 * never sees half a secret. Returns what the file holds afterwards, or an
	 * empty string if it could not be written.
	 */
	private static function create(string $file): string
	{
		$secret = bin2hex(random_bytes(32));
		$temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
		$written = Quiet::run(fn () => file_put_contents($temporary, $secret)) === strlen($secret) &&
			Quiet::run(fn () => chmod($temporary, 0600)) &&
			Quiet::run(fn () => rename($temporary, $file));
		if (!$written) {
			Quiet::run(fn () => unlink($temporary));
			return '';
		}
		return trim((string)Quiet::run(fn () => file_get_contents($file))) ?: $secret;
	}

	/**
	 * Opens $file and takes an exclusive lock on it, trying every few
	 * milliseconds for up to $wait seconds. The lock file stays; the lock ends
	 * with flock($handle, LOCK_UN) or when the request ends. Null if the file
	 * cannot be opened or the lock is not granted in time.
	 *
	 * @return resource|null
	 */
	private static function lock(string $file, float $wait)
	{
		$handle = Quiet::run(fn () => fopen($file, 'c'));
		if ($handle === false) {
			return null;
		}
		Quiet::run(fn () => chmod($file, 0600));
		$deadline = microtime(true) + $wait;
		$blocked = false;
		while (!flock($handle, LOCK_EX | LOCK_NB, $blocked)) {
			if (!$blocked || microtime(true) >= $deadline) {
				fclose($handle);
				return null;
			}
			usleep(self::LOCK_STEP);
		}
		return $handle;
	}

	/**
	 * The stored secret, or an empty string if the file is missing or too short.
	 */
	private static function read(string $file): string
	{
		$secret = is_file($file) ? trim((string)Quiet::run(fn () => file_get_contents($file))) : '';
		return strlen($secret) >= 32 ? $secret : '';
	}

	/**
	 * The signature of an address: 16 hex characters over kind, provider, id and parameters.
	 */
	public function sign(string $kind, string $provider, string $id, string $params = ''): string
	{
		$payload = implode('|', [$kind, $provider, $id, $params]);
		return substr(hash_hmac('sha256', $payload, $this->secret), 0, self::LENGTH);
	}

	/**
	 * Reads the secret from $directory/secret, creating it on first use
	 * (64 random hex characters, file mode 0600). An existing secret is read
	 * without any lock. Only when it is missing, the request takes the lock
	 * file $directory/secret.lock, reads the secret again (another request may
	 * have created it while this one waited) and creates it only if it is still
	 * missing. So two first requests at once end up with the same secret.
	 *
	 * If the secret cannot be stored, or the lock stays taken for $wait
	 * seconds, Shoji fails closed: a random secret for this request only, so no
	 * address verifies on the next request (the routes answer 404: no
	 * preview image, no player) and the problem is logged. A secret derived
	 * from something on the server would keep things working, but anyone who
	 * knows the server layout could then sign addresses.
	 */
	public static function storedSecret(string $directory, float $wait = self::LOCK_WAIT): string
	{
		$file = $directory . '/secret';
		$secret = self::read($file);
		if ($secret !== '') {
			return $secret;
		}
		if (!is_dir($directory)) {
			Quiet::run(fn () => mkdir($directory, 0700, true));
		}
		$lock = $directory . '/secret.lock';
		$handle = self::lock($lock, $wait);
		if ($handle !== null) {
			try {
				$secret = self::read($file) ?: self::create($file);
			} finally {
				flock($handle, LOCK_UN);
				fclose($handle);
			}
			if ($secret !== '') {
				return $secret;
			}
		}
		error_log('Shoji: cannot write ' . $file . ($handle === null ? ' (no lock on ' . $lock . ')' : '') . ' — placeholders will not open until shoji.secret is set or the storage directory is writable.');
		return bin2hex(random_bytes(32));
	}

	/**
	 * True if the signature belongs to the address.
	 */
	public function verify(string $signature, string $kind, string $provider, string $id, string $params = ''): bool
	{
		return strlen($signature) === self::LENGTH &&
			hash_equals($this->sign($kind, $provider, $id, $params), $signature);
	}
}
