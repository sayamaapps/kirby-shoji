<?php

namespace Shoji;

use RuntimeException;

/**
 * Signs Shoji's own addresses (/shoji/thumb|frame|script|go/…), so only
 * addresses this site rendered work. Without the signature the preview image
 * route would be an open proxy: anyone could make the server fetch preview
 * images for arbitrary ids from the provider.
 */
final class Signer
{
    /** 16 hex characters = 64 bits. Plenty for "did this site render the address". */
    public const LENGTH = 16;

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 16) {
            throw new RuntimeException('Shoji: the secret is too short');
        }
    }

    public function sign(string $kind, string $provider, string $id, string $params = ''): string
    {
        $payload = implode('|', [$kind, $provider, $id, $params]);
        return substr(hash_hmac('sha256', $payload, $this->secret), 0, self::LENGTH);
    }

    public function verify(string $signature, string $kind, string $provider, string $id, string $params = ''): bool
    {
        return strlen($signature) === self::LENGTH
            && hash_equals($this->sign($kind, $provider, $id, $params), $signature);
    }

    /**
     * Reads the secret from $directory/secret, creating it on first use
     * (64 random hex characters, file mode 0600). If the directory is not
     * writable, a value derived from the directory path is used and the
     * problem is logged: addresses keep working, but the secret is guessable
     * for someone who knows the server layout.
     */
    public static function storedSecret(string $directory): string
    {
        $file = $directory . '/secret';
        $secret = is_file($file) ? trim((string) @file_get_contents($file)) : '';
        if (strlen($secret) >= 32) {
            return $secret;
        }
        $secret = bin2hex(random_bytes(32));
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
        $written = @file_put_contents($file . '.tmp', $secret, LOCK_EX) === strlen($secret)
            && @chmod($file . '.tmp', 0600)
            && @rename($file . '.tmp', $file);
        if ($written) {
            // Two first requests at once: the last rename wins, both use the file.
            return trim((string) @file_get_contents($file)) ?: $secret;
        }
        @unlink($file . '.tmp');
        error_log('Shoji: cannot write ' . $file . ' — set shoji.secret or make the storage directory writable.');
        return hash('sha256', 'shoji|' . $directory);
    }
}
