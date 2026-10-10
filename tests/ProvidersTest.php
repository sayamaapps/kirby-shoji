<?php
/**
 * Providers without Kirby: recognising addresses, valid ids, the address
 * templates, parameter cleaning, `shoji.providers` merging, the allow
 * attribute, the signature, image type sniffing.
 *   php tests/ProvidersTest.php
 */
require_once __DIR__ . '/../src/Providers.php';
require_once __DIR__ . '/../src/Signer.php';
require_once __DIR__ . '/../src/Thumbs.php';

use Shoji\Providers;
use Shoji\Signer;
use Shoji\Thumbs;

ini_set('error_log', '/dev/null'); // the refused definitions below are logged on purpose
$checks = 0;
function expect($expected, $actual, string $what): void
{
    global $checks;
    if ($expected !== $actual) { throw new RuntimeException($what . ': ' . var_export($actual, true)); }
    $checks++;
}
function holds(bool $ok, string $what): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException($what); }
    $checks++;
}
/** Id the provider's `match` finds in $url, or null. */
function idIn(array $definition, string $url): ?string
{
    return preg_match($definition['match'], $url, $m) ? $m[1] : null;
}

try {
    $p = Providers::all();
    expect(['youtube', 'vimeo', 'digistore'], array_keys($p), 'built-in providers');
    foreach ($p as $name => $definition) {
        expect(null, Providers::problem($definition), "$name: valid definition");
    }

    // YouTube: every common address form
    $yt = $p['youtube'];
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10',
        'https://youtu.be/dQw4w9WgXcQ?si=abc',
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://www.youtube.com/live/dQw4w9WgXcQ',
    ] as $url) {
        expect('dQw4w9WgXcQ', idIn($yt, $url), "YouTube id in $url");
    }
    expect(null, idIn($yt, 'https://www.youtube.com/channel/UCBRboOIpQLe33Rwb0l9dqVw'), 'a channel is no video');
    expect(true, Providers::validId($yt, 'dQw4w9WgXcQ'), 'valid YouTube id');
    foreach (['dQw4w9WgXc', 'dQw4w9WgXcQQ', 'dQw4w9WgX"Q', '../../etc/pw', '', "dQw4w9WgXc\n"] as $bad) {
        expect(false, Providers::validId($yt, $bad), 'invalid YouTube id ' . var_export($bad, true));
    }

    // Vimeo
    $vimeo = $p['vimeo'];
    expect('76979871', idIn($vimeo, 'https://vimeo.com/76979871'), 'Vimeo plain');
    expect('76979871', idIn($vimeo, 'https://player.vimeo.com/video/76979871?h=abc'), 'Vimeo player');
    expect('76979871', idIn($vimeo, 'https://vimeo.com/channels/staffpicks/76979871'), 'Vimeo channel video');
    expect(null, idIn($vimeo, 'https://vimeo.com/76979871/abcdef1234'), 'unlisted Vimeo (with hash) is not taken over in v1');
    expect(false, Providers::validId($vimeo, '12ab'), 'Vimeo id is digits');

    // Digistore24
    $ds = $p['digistore'];
    expect('256092', idIn($ds, 'https://www.digistore24.com/product/256092/79686'), 'Digistore product address');
    expect('https://www.digistore24.com/product/256092/79686?aff=partner&cam=summer', Providers::fill($ds['embed'], '256092', ['aff' => 'partner', 'cam' => 'summer', 'form' => '79686']), 'path parameter and query');
    expect('https://www.digistore24.com/product/256092', Providers::fill($ds['embed'], '256092', []), 'optional path segment left out');
    expect('https://www.digistore24.com/product/256092', Providers::fill($ds['embed'], '256092', ['form' => 'x/y']), 'unsafe path value dropped, never a path segment');

    // Parameters: names and values cleaned, sorted
    expect(['aff' => 'a b', 'form' => '1'], Providers::params(['form' => 1, 'aff' => 'a b', 'bad-name' => 'x', 'x' => ['array'], 'empty' => '', 'long' => str_repeat('a', 201)]), 'parameters cleaned and sorted');
    expect('https://x.example/e?q=1&aff=a%20b', Providers::fill('https://x.example/e?q=1', 'id', ['aff' => 'a b']), 'query appended with &');

    // shoji.providers: change a field, remove one, add one, refuse broken ones
    $custom = Providers::all([
        'vimeo'   => false,
        'youtube' => ['embed' => 'https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0'],
        'spotify' => ['name' => 'Spotify', 'company' => 'Spotify', 'id' => '/^[A-Za-z0-9]{22}$/', 'embed' => 'https://open.spotify.com/embed/track/{id}'],
        'broken'  => ['name' => 'Broken', 'company' => 'X', 'id' => '/^\d+$/', 'embed' => 'http://insecure.example/{id}'],
        'Bad Name' => ['name' => 'x'],
    ]);
    expect(['youtube', 'digistore', 'spotify'], array_keys($custom), 'removed, added, refused');
    expect('https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0', $custom['youtube']['embed'], 'single field replaced');
    expect('YouTube', $custom['youtube']['name'], 'other fields kept');

    // allow: every feature for any origin (the frame is redirected from our own address)
    expect('autoplay *; encrypted-media *; fullscreen *; picture-in-picture *', Providers::allow($yt), 'allow attribute');
    expect('payment *', Providers::allow(['allow' => 'payment; evil="x"; ']), 'only plain feature names');

    // Signer
    $signer = new Signer(str_repeat('k', 32));
    $s = $signer->sign('frame', 'youtube', 'dQw4w9WgXcQ', '');
    expect(16, strlen($s), 'signature length');
    expect(true, $signer->verify($s, 'frame', 'youtube', 'dQw4w9WgXcQ', ''), 'signature verifies');
    expect(false, $signer->verify($s, 'thumb', 'youtube', 'dQw4w9WgXcQ', ''), 'bound to the kind');
    expect(false, $signer->verify($s, 'frame', 'youtube', 'dQw4w9WgXcQ', 'aff=x'), 'bound to the parameters');
    expect(false, (new Signer(str_repeat('j', 32)))->verify($s, 'frame', 'youtube', 'dQw4w9WgXcQ', ''), 'bound to the secret');
    expect(false, $signer->verify('', 'frame', 'youtube', 'dQw4w9WgXcQ', ''), 'empty signature');
    $dir = sys_get_temp_dir() . '/shoji-secret-' . bin2hex(random_bytes(4));
    $first = Signer::storedSecret($dir);
    expect(64, strlen($first), 'secret created');
    expect($first, Signer::storedSecret($dir), 'secret stable');
    expect('600', substr(sprintf('%o', fileperms($dir . '/secret')), -3), 'secret file mode 0600');
    unlink($dir . '/secret');
    rmdir($dir);
    // Cannot be stored (a file where the directory should be; works as root too): fail closed.
    $blocker = tempnam(sys_get_temp_dir(), 'shoji-blocker');
    $ephemeral = @Signer::storedSecret($blocker . '/storage');
    expect(64, strlen($ephemeral), 'unwritable: still a full-length secret');
    holds($ephemeral !== @Signer::storedSecret($blocker . '/storage'), 'unwritable: a new random secret per call, nothing derivable');
    unlink($blocker);

    // Image type from the bytes
    expect('jpg', Thumbs::sniff("\xFF\xD8\xFF\xE0rest"), 'JPEG');
    expect('png', Thumbs::sniff("\x89PNG\r\n\x1A\nrest"), 'PNG');
    expect('webp', Thumbs::sniff('RIFF1234WEBPVP8 '), 'WebP');
    expect(null, Thumbs::sniff('<svg onload="alert(1)">'), 'SVG/HTML refused');

    echo "ProvidersTest: $checks checks passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ProvidersTest FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
