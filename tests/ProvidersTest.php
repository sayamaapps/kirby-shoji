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
    expect(['youtube', 'youtube-playlist', 'vimeo', 'digistore'], array_keys($p), 'built-in providers');
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
    expect(false, Providers::validId($yt, 'videoseries'), 'videoseries is the playlist player, not a video id');
    expect(true, Providers::validId($yt, 'videoserie2'), 'other eleven characters are fine');

    // YouTube playlists: playlist?list= and embed/videoseries?list=, never as a video
    $pl = $p['youtube-playlist'];
    foreach ([
        'https://www.youtube.com/playlist?list=PL1234567890abcdef',
        'https://youtube.com/playlist?si=abc&list=PL1234567890abcdef&x=1',
        'https://m.youtube.com/playlist?list=PL1234567890abcdef',
        'https://www.youtube.com/embed/videoseries?list=PL1234567890abcdef',
        'https://www.youtube-nocookie.com/embed/videoseries?si=abc&list=PL1234567890abcdef',
    ] as $url) {
        expect('PL1234567890abcdef', idIn($pl, $url), "playlist id in $url");
    }
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL1234567890abcdef', // a video in a playlist is the video
        'https://www.youtube.com/embed/videoseries', 'https://www.youtube.com/playlist', 'https://www.youtube.com/playlist?list=', 'https://www.youtube.com/playlist?list=PL12',
        'https://www.youtube.com/playlist?xlist=PL1234567890abcdef', 'https://www.youtube.com/playlist#list=PL1234567890abcdef', 'https://youtu.be/PL1234567890abcdef',
    ] as $url) {
        expect(null, idIn($pl, $url), "no playlist in $url");
    }
    expect('videoseries', idIn($yt, 'https://www.youtube.com/embed/videoseries?list=PL1234567890abcdef'), 'the video pattern still finds videoseries, the id check refuses it');
    expect('https://www.youtube-nocookie.com/embed/videoseries?list=PL1234567890abcdef&autoplay=1', Providers::fill($pl['embed'], 'PL1234567890abcdef', []), 'playlist player address');
    expect('https://www.youtube.com/playlist?list=PL1234567890abcdef', Providers::fill($pl['link'], 'PL1234567890abcdef', []), 'playlist link');
    expect(true, Providers::validId($pl, 'PL1234567890abcdef') && !Providers::validId($pl, 'videoseries') && !Providers::validId($pl, 'PL12') && !Providers::validId($pl, 'PL1234567890abc"def'), 'playlist ids');
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

    // Vimeo: the hash of an unlisted video travels as h, in the player, the link and the oEmbed address
    $urlParams = fn (array $definition, string $url) => preg_match($definition['match'], $url, $m) ? $definition['urlParams']($url, $m) : 'no match';
    expect(['h' => 'abcdef1234'], $urlParams($vimeo, 'https://player.vimeo.com/video/123456789?h=abcdef1234'), 'Vimeo h from the player address');
    expect(['h' => 'abcdef1234'], $urlParams($vimeo, 'https://vimeo.com/123456789?share=copy&h=abcdef1234#t=1'), 'Vimeo h among other parameters');
    expect([], $urlParams($vimeo, 'https://vimeo.com/76979871'), 'Vimeo without h');
    expect([], $urlParams($vimeo, 'https://vimeo.com/76979871?h='), 'Vimeo with an empty h');
    foreach (['https://vimeo.com/123456789?h=ab', 'https://vimeo.com/123456789?h=ab%2Fcdef12', 'https://vimeo.com/123456789?h[]=abcdef1234', 'https://vimeo.com/123456789?h=' . str_repeat('a', 33)] as $url) {
        expect(null, $urlParams($vimeo, $url), "Vimeo h refused, address not taken over: $url");
    }
    expect('https://player.vimeo.com/video/123456789?autoplay=1&dnt=1&h=abcdef1234', Providers::fill($vimeo['embed'], '123456789', ['h' => 'abcdef1234']), 'Vimeo player address with h');
    expect('https://player.vimeo.com/video/123456789?autoplay=1&dnt=1', Providers::fill($vimeo['embed'], '123456789', []), 'Vimeo player address without h, as before');
    expect('https://vimeo.com/123456789/abcdef1234', Providers::fill($vimeo['link'], '123456789', ['h' => 'abcdef1234']), 'Vimeo link with h: the unlisted address');
    expect('https://vimeo.com/123456789', Providers::fill($vimeo['link'], '123456789', []), 'Vimeo link without h, as before');
    $asked = [];
    $fetch = function (string $url) use (&$asked): array { $asked[] = $url; return [200, '{"thumbnail_url":"https://i.example/t.jpg"}']; };
    expect(['https://i.example/t.jpg'], $vimeo['thumbnails']('123456789', $fetch, ['h' => 'abcdef1234']), 'Vimeo oEmbed answer');
    expect(['https://vimeo.com/api/oembed.json?width=1280&url=https%3A%2F%2Fvimeo.com%2F123456789%2Fabcdef1234'], $asked, 'oEmbed with the full unlisted address');
    $asked = [];
    $vimeo['thumbnails']('123456789', $fetch);
    $vimeo['thumbnails']('123456789', $fetch, ['aff' => 'x', 'h' => 'a/b']);
    expect(['https://vimeo.com/api/oembed.json?width=1280&url=https%3A%2F%2Fvimeo.com%2F123456789', 'https://vimeo.com/api/oembed.json?width=1280&url=https%3A%2F%2Fvimeo.com%2F123456789'], $asked, 'oEmbed without h (or with an unsafe one) as before');

    // Digistore24
    $ds = $p['digistore'];
    expect(['form' => '78901', 'aff' => 'partner', 'cam' => 'summer'], $urlParams($ds, 'https://www.digistore24.com/product/123456/78901?aff=partner&cam=summer'), 'Digistore form, affiliate, campaign');
    expect(['aff' => 'partner'], $urlParams($ds, 'https://www.digistore24.com/product/123456?aff=partner&cam='), 'Digistore affiliate only, empty campaign ignored');
    expect(['form' => '78901'], $urlParams($ds, 'https://www.digistore24.com/product/123456/78901/#top'), 'Digistore form with a trailing slash');
    expect([], $urlParams($ds, 'https://www.digistore24.com/product/123456'), 'Digistore plain');
    expect([], $urlParams($ds, 'https://www.digistore24.com/redir/123456'), 'Digistore redir without more');
    expect(['aff' => 'a b&c'], $urlParams($ds, 'https://www.digistore24.com/product/123456?aff=a%20b%26c'), 'Digistore values decoded');
    foreach ([
        'https://www.digistore24.com/redir/123456/partner/summer', 'https://www.digistore24.com/redir/123456/78901', // path of a redir address: other things than a form
        'https://www.digistore24.com/product/123456/abc', 'https://www.digistore24.com/product/123456/78901/2', 'https://www.digistore24.com/product/123456/12345678901',
        'https://www.digistore24.com/product/123456?aff[]=x', 'https://www.digistore24.com/product/123456?aff=' . str_repeat('a', 201),
    ] as $url) {
        expect(null, $urlParams($ds, $url), "Digistore form not carried, address not taken over: $url");
    }
    expect('256092', idIn($ds, 'https://www.digistore24.com/product/256092/79686'), 'Digistore product address');
    expect('https://www.digistore24.com/product/256092/79686?aff=partner&cam=summer', Providers::fill($ds['embed'], '256092', ['aff' => 'partner', 'cam' => 'summer', 'form' => '79686']), 'path parameter and query');
    expect('https://www.digistore24.com/product/256092', Providers::fill($ds['embed'], '256092', []), 'optional path segment left out');
    expect('https://www.digistore24.com/product/256092', Providers::fill($ds['embed'], '256092', ['form' => 'x/y']), 'unsafe path value dropped, never a path segment');

    // Protected hosts: the host decides, not the form of the address, and not look-alikes
    foreach ([
        'https://www.youtube.com/playlist?list=PL1234567890abcdef' => 'youtube', 'https://youtube.com/@gasthausmuster' => 'youtube',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ' => 'youtube', 'https://youtu.be/dQw4w9WgXcQ' => 'youtube',
        'https://www.youtube-nocookie.com/embed/videoseries?list=PL1234567890abcdef' => 'youtube', 'https://music.youtube.com/x' => 'youtube',
        'HTTPS://WWW.YouTube.com/x' => 'youtube', 'https://www.youtube.com./x' => 'youtube', 'https://user:pw@www.youtube.com:443/x' => 'youtube',
        '//www.youtube.com/x' => 'youtube', 'ftp://youtu.be/x' => 'youtube', ' https://youtu.be/x ' => 'youtube',
        'https://vimeo.com/showcase/1/video/123456789' => 'vimeo', 'https://player.vimeo.com/video/12345' => 'vimeo', 'https://www.vimeo.com/12345' => 'vimeo',
        'https://www.digistore24.com/product/123456' => 'digistore', 'https://checkout.digistore24.com/x' => 'digistore', 'https://digistore24.com/clip.mp4' => 'digistore',
    ] as $url => $key) {
        expect($key, Providers::protectedProvider((string) $url), "protected provider of $url");
        expect(true, Providers::isProtectedHost((string) $url), "protected host: $url");
    }
    foreach ([
        'https://notyoutube.com/watch?v=dQw4w9WgXcQ', 'https://youtube.com.example.org/x', 'https://example.com/?u=youtube.com/watch?v=dQw4w9WgXcQ',
        'https://example.com/vimeo.com/12345', 'https://evilvimeo.com/12345', 'https://vimeo.com.evil.example/12345', 'https://www.digistore24-scripts.com/x.js',
        'https://example.com/clip.mp4', 'clip.mp4', 'youtube.com/watch?v=dQw4w9WgXcQ', '/media/youtube.com/x.mp4', '', 'https://', 'https://www.youtube.com@example.org/x',
    ] as $url) {
        expect(false, Providers::isProtectedHost($url), "not a protected host: $url");
    }
    // The plain link: the address itself, http(s) only, nothing else
    expect('https://vimeo.com/12345', Providers::plainLink('https://vimeo.com/12345'), 'link: the address itself');
    expect('https://vimeo.com/12345', Providers::plainLink("  https://vimeo.com/12345\n"), 'link: trimmed');
    expect('http://vimeo.com/12345', Providers::plainLink('http://vimeo.com/12345'), 'link: http stays http');
    expect('https://www.youtube.com/x', Providers::plainLink('//www.youtube.com/x'), 'link: scheme-relative becomes https');
    foreach (['javascript://www.youtube.com/%0aalert(1)', 'ftp://youtu.be/x', 'data://vimeo.com/x', 'https://vimeo.com/a b', "https://vimeo.com/a\nb", 'https://vimeo.com/a"onclick="x' . "\x01", 'https://example.com/x', 'vimeo.com/12345'] as $url) {
        expect(null, Providers::plainLink($url), "no link for $url");
    }

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
    expect(['youtube', 'youtube-playlist', 'digistore', 'spotify'], array_keys($custom), 'removed, added, refused');
    expect('https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0', $custom['youtube']['embed'], 'single field replaced');
    expect('YouTube', $custom['youtube']['name'], 'other fields kept');

    expect("'urlParams' must be a closure", Providers::problem(['urlParams' => 'nope'] + $vimeo), 'urlParams must be a closure');

    // Kind html: the site's snippet instead of an iframe address, scripts and stylesheets as lists
    $html = ['name' => 'Brevo', 'company' => 'Brevo', 'kind' => 'html', 'id' => '/^[a-z]+$/', 'snippet' => 'forms/{id}',
        'script' => ['https://sibforms.com/forms/end-form/build/main.js'], 'style' => 'https://sibforms.com/forms/end-form/build/sib-styles.css'];
    expect(null, Providers::problem($html), 'html: valid without embed');
    expect("'snippet' is missing", Providers::problem(array_diff_key($html, ['snippet' => 1])), 'html: snippet required');
    expect("'embed' is missing", Providers::problem(['kind' => 'form'] + array_diff_key($html, ['kind' => 1])), 'other kinds: embed still required');
    foreach (['../secret', 'forms/../../x', 'forms/{id}.php', '/abs', 'forms//x', 'forms/{other}'] as $bad) {
        holds(Providers::problem(['snippet' => $bad] + $html) !== null, "html: snippet name refused: $bad");
    }
    expect(['https://a.example/1.js', 'https://a.example/2.js'], Providers::assets(['script' => ['https://a.example/1.js', 'https://a.example/2.js']], 'script'), 'script list kept in order');
    expect(['https://a.example/1.js'], Providers::assets(['script' => 'https://a.example/1.js'], 'script'), 'a single script is a list of one');
    expect([], Providers::assets([], 'style'), 'no stylesheets');
    foreach ([['http://a.example/x.js'], ['x' => 'https://a.example/x.js'], [['https://a.example/x.js']], array_fill(0, 11, 'https://a.example/x.js')] as $bad) {
        holds(Providers::problem(['script' => $bad] + $html) !== null, 'script list refused: ' . json_encode($bad));
        holds(Providers::problem(['style' => $bad] + $html) !== null, 'style list refused: ' . json_encode($bad));
    }
    expect(['youtube', 'youtube-playlist', 'vimeo', 'digistore', 'brevo'], array_keys(Providers::all(['brevo' => $html])), 'html provider added through shoji.providers');

    // shoji.open: provider name => bool, only known providers, the rest dropped and logged
    $log = tempnam(sys_get_temp_dir(), 'shoji-log');
    ini_set('error_log', $log);
    $withBrevo = Providers::all(['brevo' => $html]);
    expect([], Providers::open(null, $withBrevo), 'open: not set, nothing opens');
    expect([], Providers::open([], $withBrevo), 'open: empty, nothing opens');
    expect('', (string) file_get_contents($log), 'open: nothing to log without the option');
    expect(['brevo' => true, 'vimeo' => false], Providers::open(['brevo' => true, 'vimeo' => false], $withBrevo), 'open: built-in and own providers, true and false');
    expect(['digistore' => true], Providers::open(['nope' => true, 'youtube' => 'yes', 'vimeo' => 1, 0 => true, 'digistore' => true], $withBrevo), 'open: unknown names and non-bool values dropped');
    $logged = (string) file_get_contents($log);
    holds(str_contains($logged, "Shoji: ignoring shoji.open for 'nope' (no such provider)"), 'open: unknown provider logged');
    holds(str_contains($logged, 'Shoji: ignoring shoji.open for youtube (value must be true or false)') && str_contains($logged, 'shoji.open for vimeo (value'), 'open: non-bool values logged');
    holds(str_contains($logged, 'Shoji: ignoring shoji.open for 0 (no such provider)'), 'open: a list entry is no provider name');
    holds(!str_contains($logged, 'digistore'), 'open: valid entries not logged');
    expect([], Providers::open(['brevo' => true], Providers::all()), 'open: a provider that is not defined (or removed) never opens');
    file_put_contents($log, '');
    expect([], Providers::open(true, $withBrevo), 'open: no switch for every provider at once');
    holds(str_contains((string) file_get_contents($log), 'Shoji: ignoring shoji.open (must be an array'), 'open: a non-array logged');
    ini_set('error_log', '/dev/null');
    unlink($log);

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
    $script = $signer->sign('script', 'brevo', 'newsletter', '');
    expect(false, $signer->verify($script, 'script', 'brevo', 'newsletter', 'n=1'), 'bound to the script index');
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
