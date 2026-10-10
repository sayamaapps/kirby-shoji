<?php
/**
 * The plugin with real Kirby, in a throwaway directory: a page with every
 * way content gets in (snippet by address, by provider and id, with
 * parameters; (video:) tag; video block) is rendered and must name no
 * provider host before the click. Further: escaping, no inline script, the
 * switch (off = plain link / Kirby's own tag and block / routes 404), the
 * signed routes (redirects, preview image cache, tampering), `shoji.url`,
 * `shoji.texts`, the privacy link, and that a site's own
 * site/snippets/shoji/embed.php wins.
 *   php tests/KirbyTest.php
 */
$base = dirname(__DIR__);
if (!is_file($base . '/vendor/autoload.php')) { fwrite(STDERR, "Composer dependencies missing; run composer install first\n"); exit(1); }
require $base . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Http\Response;
use Kirby\Template\Snippet;
use Shoji\Http;

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
function removeTree(string $path): void
{
    if (!is_dir($path)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
}

/** Every host a provider uses. None of them may appear in a page before the click. */
const FOREIGN = '~(?:youtube(?:-nocookie)?\.com|youtu\.be|ytimg\.com|googlevideo\.com|vimeo\.com|vimeocdn\.com|digistore24(?:-scripts)?\.com)~i';

$tmp = sys_get_temp_dir() . '/shoji-kirby-' . bin2hex(random_bytes(6));
$hostile = '"><script>alert(1)</script>';
$blocks = json_encode([[
    'id' => 'b1', 'type' => 'video', 'isHidden' => false,
    'content' => ['location' => 'web', 'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'caption' => 'Block caption'],
]]);

/** New Kirby instance with these options (and request query, for routes). */
$app = function (array $options, array $query = []) use ($tmp, $hostile, $blocks): App {
    Snippet::$cache = []; // Kirby remembers snippet paths per process
    return new App([
        'roots' => [
            'index' => $tmp . '/public', 'content' => $tmp . '/content', 'site' => $tmp . '/site',
            'storage' => $tmp . '/storage', 'media' => $tmp . '/public/media',
        ],
        'request' => ['query' => $query],
        'options' => ['url' => 'https://site.example'] + $options,
        'site' => ['children' => [
            [
                'slug' => 'home', 'template' => 'home',
                'content' => [
                    'title'  => 'Home',
                    'yt'     => 'https://youtu.be/dQw4w9WgXcQ',
                    'ytTitle' => $hostile,
                    'text'   => "(video: https://www.youtube.com/watch?v=jNQXAC9IVRw caption: Tag caption)\n\n(video: https://vimeo.com/76979871)",
                    'blocks' => $blocks,
                ],
            ],
            ['slug' => 'datenschutz', 'template' => 'default', 'content' => ['title' => 'Datenschutz']],
        ]],
    ]);
};
/** The rendered home page. */
$render = fn (array $options): string => (string) $app($options)->page('home')->render();
/** Calls a /shoji/… address of the rendered page as a fresh request. */
$call = function (string $url, array $options) use ($app): mixed {
    $parts = parse_url(html_entity_decode($url));
    parse_str($parts['query'] ?? '', $query);
    $kirby = $app($options, $query);
    return $kirby->call(ltrim($parts['path'], '/'), 'GET');
};
/** All values of one attribute in $html, entities decoded. */
$attrs = function (string $html, string $name): array {
    preg_match_all('/\s' . preg_quote($name, '/') . '="([^"]*)"/', $html, $m);
    return array_map('html_entity_decode', $m[1]);
};

try {
    foreach (['public', 'content', 'site/templates', 'site/snippets', 'storage'] as $dir) {
        mkdir($tmp . '/' . $dir, 0700, true);
    }
    // The plugin as Kirby loads it: through site/plugins.
    mkdir($tmp . '/site/plugins');
    symlink($base, $tmp . '/site/plugins/shoji');
    file_put_contents($tmp . '/site/templates/default.php', '<?= $page->title() ?>');
    file_put_contents($tmp . '/site/templates/home.php', <<<'PHP'
<!doctype html><html><body>
<?php snippet('shoji/embed', ['url' => $page->yt()->value(), 'title' => $page->ytTitle()->value()]) ?>
<?php snippet('shoji/embed', ['provider' => 'vimeo', 'id' => '76979871']) ?>
<?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'params' => ['form' => '78901', 'aff' => 'partner'], 'label' => 'Buy now', 'buttonClass' => 'buy-btn', 'frameClass' => 'ds24_payIFrame', 'attrs' => ['data-product' => '123456', 'onclick' => 'evil()']]) ?>
<?php snippet('shoji/embed', ['provider' => 'youtube', 'id' => 'bad"id<x>']) ?>
<?= $page->text()->kirbytext() ?>
<?= $page->blocks()->toBlocks() ?>
</body></html>
PHP);
    // Loading the plugin defines its functions; one instance is enough for that.
    $app([]);

    // 1. Off by default: plain links, Kirby's own tag and block, no routes.
    $html = $render([]);
    holds(!str_contains($html, 'data-shoji'), 'off: no placeholder');
    holds(str_contains($html, 'class="shoji-link" href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"'), 'off: the snippet prints a plain link');
    holds(substr_count($html, '<iframe') === 3, 'off: the (video:) tags and the video block are Kirby\'s own iframes');
    holds(!str_contains($html, 'shoji.js'), 'off: no script');
    $off = $call('/shoji/go/youtube/dQw4w9WgXcQ?s=0000000000000000', []);
    holds(!($off instanceof Response && $off->code() === 302), 'off: routes do not redirect');

    // 2. On: the page names no provider host.
    $on = ['shoji.active' => true];
    $html = $render($on);
    holds(preg_match(FOREIGN, $html, $hit) === 0, 'on: no provider host in the page before the click, found ' . ($hit[0] ?? ''));
    expect(6, substr_count($html, ' data-shoji '), 'six placeholders: 3 snippets, 2 tags, 1 block (the invalid id prints nothing)');
    holds(!str_contains($html, '<iframe'), 'on: no iframe before the click');
    foreach (array_merge($attrs($html, 'src'), $attrs($html, 'href'), $attrs($html, 'data-shoji-frame'), $attrs($html, 'data-shoji-script')) as $url) {
        holds(str_starts_with($url, 'https://site.example/'), "every address points to the site itself: $url");
    }

    // Content Security Policy: one external script, no inline script, no handlers, no style attributes.
    preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $html, $scripts, PREG_SET_ORDER);
    expect(1, count($scripts), 'exactly one script tag on a page with six placeholders');
    holds(preg_match('~\ssrc="https://site\.example/media/plugins/sayamaapps/shoji/[^"]+/shoji\.js"~', $scripts[0][1]) === 1 && trim($scripts[0][2]) === '', 'the script is the external plugin asset');
    holds(preg_match('~\son[a-z]+\s*=~i', $html) === 0, 'no inline event handler (the onclick attribute was dropped)');
    holds(preg_match('~\sstyle\s*=~i', $html) === 0, 'no style attribute');

    // Escaping: the hostile title stays text.
    holds(!str_contains($html, '<script>alert(1)'), 'hostile title not executed');
    holds(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'hostile title escaped');
    holds(!str_contains($html, 'bad"id'), 'invalid id prints nothing');

    // Texts, privacy link, extras
    holds(str_contains($html, 'Play video: &quot;&gt;&lt;script&gt;'), 'button names the title (English default)');
    holds(str_contains($html, 'The video is loaded from YouTube only after you click. Google then learns your IP address.'), 'note names provider and company');
    holds(str_contains($html, '<a class="shoji-privacy" href="https://site.example/datenschutz">Privacy policy</a>'), 'privacy link found automatically');
    holds(str_contains($html, 'class="shoji-button buy-btn"'), 'extra button class');
    holds(str_contains($html, '>Buy now</a>'), 'own button label');
    holds(str_contains($html, 'data-product="123456"'), 'data attribute passed through');
    holds(str_contains($html, 'data-shoji-frame-class="ds24_payIFrame"'), 'frame class passed on');
    holds(str_contains($html, 'Block caption') && str_contains($html, 'Tag caption'), 'captions of block and tag kept');
    holds(str_contains($html, 'width="1280" height="720" loading="lazy"'), 'preview image with size, lazy');

    $german = $render($on + ['shoji.language' => 'de', 'shoji.texts' => ['de' => ['note.video' => 'Erst nach Deinem Klick lädt {name}.']], 'shoji.privacy' => false]);
    holds(str_contains($german, 'Video abspielen'), 'German button');
    holds(str_contains($german, 'Erst nach Deinem Klick lädt Vimeo.'), 'shoji.texts override');
    holds(!str_contains($german, 'shoji-privacy'), 'shoji.privacy false: no link');

    // 3. Routes: signed, redirecting to the provider.
    $frames = $attrs($html, 'data-shoji-frame');
    $links = $attrs($html, 'href');
    $frameOf = fn (string $needle) => current(array_filter($frames, fn ($u) => str_contains($u, $needle)));
    $r = $call($frameOf('/frame/youtube/dQw4w9WgXcQ'), $on);
    expect(302, $r->code(), 'frame route redirects');
    expect('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', $r->header('Location'), 'to the nocookie player');
    expect('no-store', $r->header('Cache-Control'), 'redirect not cached');
    $ds = $frameOf('/frame/digistore/123456');
    holds(str_contains($ds, 'aff%3Dpartner'), 'parameters travel in the signed own address');
    expect('https://www.digistore24.com/product/123456/78901?aff=partner', $call($ds, $on)->header('Location'), 'digistore frame with form and affiliate');
    $script = $attrs($html, 'data-shoji-script')[0];
    expect('https://www.digistore24-scripts.com/service/digistore.js', $call($script, $on)->header('Location'), 'provider script');
    $go = current(array_filter($links, fn ($u) => str_contains($u, '/go/vimeo/')));
    expect('https://vimeo.com/76979871', $call($go, $on)->header('Location'), 'plain link leads to the provider');

    // Tampering
    expect(404, $call(str_replace('aff%3Dpartner', 'aff%3Dthief', $ds), $on)->code(), 'changed parameter refused');
    expect(404, $call(preg_replace('/s=[0-9a-f]{16}/', 's=0123456789abcdef', $ds), $on)->code(), 'wrong signature refused');
    expect(404, $call(str_replace('/frame/', '/go/', $frameOf('/frame/youtube/dQw4w9WgXcQ')) . '', $on)->code(), 'signature bound to the kind');
    expect(404, $call('/shoji/frame/youtube/..%2F..%2Fetc?s=0000000000000000', $on)->code(), 'path tricks refused');
    expect(404, $call('/shoji/frame/nope/dQw4w9WgXcQ?s=0000000000000000', $on)->code(), 'unknown provider refused');

    // shoji.url: the site changes the provider address at click time.
    $hooked = $on + ['shoji.url' => fn (string $url, \Shoji\Embed $embed, string $kind) => $embed->provider === 'digistore' && $kind === 'frame' ? $url . '&cam=fromhook' : $url];
    expect('https://www.digistore24.com/product/123456/78901?aff=partner&cam=fromhook', $call($ds, $hooked)->header('Location'), 'shoji.url hook');
    $bad = $on + ['shoji.url' => fn () => 'javascript:alert(1)'];
    expect(404, $call($ds, $bad)->code(), 'hook returning a non-https address is refused');

    // 4. Preview image: fetched once, cached, served from the site.
    $thumb = current(array_filter($attrs($html, 'src'), fn ($u) => str_contains($u, '/thumb/youtube/dQw4w9WgXcQ')));
    $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 2000);
    $asked = [];
    Http::$fake = function (string $url) use (&$asked, $jpeg): array {
        $asked[] = $url;
        return str_contains($url, 'maxresdefault') ? [404, str_repeat('p', 1100)] : [200, $jpeg];
    };
    $r = $call($thumb, $on);
    expect(200, $r->code(), 'preview image served');
    expect('image/jpeg', $r->type(), 'as JPEG');
    expect($jpeg, $r->body(), 'the fetched bytes');
    expect(['https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'], $asked, 'maxres missing, hq taken');
    holds(is_file($tmp . '/storage/shoji/thumbs/youtube/dQw4w9WgXcQ.jpg'), 'cached in storage/shoji/thumbs');
    $asked = [];
    $call($thumb, $on);
    expect([], $asked, 'second request from the cache');

    // Nothing usable: a blank image, remembered for a day.
    $vimeoThumb = current(array_filter($attrs($html, 'src'), fn ($u) => str_contains($u, '/thumb/vimeo/')));
    Http::$fake = function (string $url) use (&$asked): array {
        $asked[] = $url;
        return [200, '<html>not an image</html>' . str_repeat(' ', 2000)];
    };
    $r = $call($vimeoThumb, $on);
    expect('image/svg+xml', $r->type(), 'blank image instead of a broken one');
    holds(is_file($tmp . '/storage/shoji/thumbs/vimeo/76979871.miss'), 'miss remembered');
    $asked = [];
    $call($vimeoThumb, $on);
    expect([], $asked, 'no new fetch within a day after a miss');
    Http::$fake = null;

    // 5. Open mode: content right away, still only own addresses.
    file_put_contents($tmp . '/site/templates/home.php', "<?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'open' => true]) ?>");
    $open = $render($on);
    holds(str_contains($open, '<iframe class="shoji-frame" src="https://site.example/shoji/frame/digistore/123456'), 'open: iframe through the own address');
    holds(str_contains($open, '<script src="https://site.example/shoji/script/digistore/123456'), 'open: provider script through the own address');
    holds(preg_match(FOREIGN, $open) === 0, 'open: still no provider host in the page');

    // 6. A site's own snippet wins.
    file_put_contents($tmp . '/site/templates/home.php', "<?php snippet('shoji/embed', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']) ?>");
    mkdir($tmp . '/site/snippets/shoji');
    file_put_contents($tmp . '/site/snippets/shoji/embed.php', '<div class="own"><?= esc(shoji_embed($provider, $id)->frameUrl()) ?></div>');
    $own = $render($on);
    holds(str_starts_with($own, '<div class="own">https://site.example/shoji/frame/youtube/dQw4w9WgXcQ'), 'site snippet takes over, building on shoji_embed()');

    echo "KirbyTest: $checks checks passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'KirbyTest FAILED: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n");
    $failed = true;
} finally {
    removeTree($tmp);
}
exit(isset($failed) ? 1 : 0);
