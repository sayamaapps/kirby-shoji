<?php
/**
 * The plugin with real Kirby, in a throwaway directory: a page with every
 * way content gets in (snippet by address, by provider and id, with
 * parameters; (video:) tag; video block) is rendered and must name no
 * provider host before the click. Further: escaping, no inline script, the
 * switch (off = plain link / Kirby's own tag and block / routes 404), the
 * signed routes (redirects, preview image cache, tampering), `shoji.url`,
 * `shoji.texts`, the privacy link, that a site's own
 * site/snippets/shoji/embed.php wins, and kind 'html' (a Brevo form from a
 * site snippet: nothing of it in the page, served only by its signed
 * address, scripts and stylesheets through their own addresses), and
 * `shoji.open` (a switch per provider, the call's own `open` winning in
 * both directions, invalid entries logged) with `$site->shojiOpen()`, and
 * the addresses of a protected provider Shoji cannot embed: the (video:) tag
 * and the video block print a note and a plain link, never Kirby's iframe.
 *   php tests/KirbyTest.php
 */
$base = dirname(__DIR__);
if (!is_file($base . '/vendor/autoload.php')) { fwrite(STDERR, "Composer dependencies missing; run composer install first\n"); exit(1); }
require $base . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Cms\Block;
use Kirby\Cms\Html;
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
const FOREIGN = '~(?:youtube(?:-nocookie)?\.com|youtu\.be|ytimg\.com|googlevideo\.com|vimeo\.com|vimeocdn\.com|digistore24(?:-scripts)?\.com|sibforms\.com|brevo\.com)~i';

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

    // 7. Kind html: a Brevo form with a hidden campaign field, from the site's own snippet.
    unlink($tmp . '/site/snippets/shoji/embed.php');
    mkdir($tmp . '/site/snippets/forms');
    file_put_contents($tmp . '/site/snippets/forms/newsletter.php', <<<'PHP'
<div class="sib-form"><div id="sib-form-container" class="sib-form-container">
<form id="sib-form" method="POST" action="https://abc123.sibforms.com/serve/MUIFAbC" data-type="subscription">
<input class="input" type="text" id="EMAIL" name="EMAIL" autocomplete="off" placeholder="EMAIL" data-required="true" required>
<input type="hidden" name="CAM" value="<?= esc($params['cam'] ?? '') ?>">
<input type="text" name="email_address_check" value="" class="input--hidden">
<input type="hidden" name="locale" value="de">
<button class="sib-form-block__button" form="sib-form" type="submit">Anmelden</button>
</form></div></div>
PHP);
    $brevo = [
        'name' => 'Brevo', 'company' => 'Brevo', 'kind' => 'html', 'id' => '/^[a-z-]+$/', 'snippet' => 'forms/{id}',
        'script' => ['https://sibforms.com/forms/end-form/build/main.js', 'https://sibforms.com/forms/end-form/build/extra.js'],
        'style'  => 'https://sibforms.com/forms/end-form/build/sib-styles.css',
    ];
    $html = $on + ['shoji.providers' => ['brevo' => $brevo]];
    file_put_contents($tmp . '/site/templates/home.php', <<<'PHP'
<!doctype html><html><body>
<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'newsletter', 'params' => ['cam' => 'spring-26']]) ?>
<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'missing']) ?>
</body></html>
PHP);
    $page = $render($html);
    holds(preg_match(FOREIGN, $page, $hit) === 0, 'html: no provider host in the page before the click, found ' . ($hit[0] ?? ''));
    holds(!str_contains($page, '<form') && !str_contains($page, 'sib-form') && !str_contains($page, 'CAM'), 'html: the form markup is not in the page before the click');
    expect(2, substr_count($page, ' data-shoji '), 'html: two placeholders');
    holds(str_contains($page, 'class="shoji shoji-html shoji-brevo" data-shoji data-shoji-provider="brevo" data-shoji-html="https://site.example/shoji/html/brevo/newsletter?p=cam%3Dspring-26&amp;s='), 'html: container with kind, provider and the own markup address');
    holds(!str_contains($page, 'data-shoji-frame') && !str_contains($page, 'data-shoji-allow'), 'html: no iframe attributes');
    holds(str_contains($page, '>Load form</a>'), 'html: button like a form');
    holds(str_contains($page, 'The form is loaded only after you click, with scripts from Brevo. Brevo then learns your IP address. <a class="shoji-privacy" href="https://site.example/datenschutz">Privacy policy</a>'), 'html: note with name, company and privacy link');
    foreach (array_merge($attrs($page, 'src'), $attrs($page, 'href'), $attrs($page, 'data-shoji-html')) as $url) {
        holds(str_starts_with($url, 'https://site.example/'), "html: every address points to the site itself: $url");
    }
    $scriptList = explode(' ', $attrs($page, 'data-shoji-script')[0]);
    expect(2, count($scriptList), 'html: two provider scripts, space-separated');
    holds(str_starts_with($scriptList[0], 'https://site.example/shoji/script/brevo/newsletter?s=') && str_starts_with($scriptList[1], 'https://site.example/shoji/script/brevo/newsletter?n=1&s='), 'html: own script addresses, the second with its index');
    $styleUrl = $attrs($page, 'data-shoji-style')[0];
    holds(str_starts_with($styleUrl, 'https://site.example/shoji/style/brevo/newsletter?s='), 'html: own stylesheet address');
    preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $page, $scripts, PREG_SET_ORDER);
    expect(1, count($scripts), 'html: only shoji.js as script tag, no provider script before the click');
    holds(trim($scripts[0][2]) === '' && preg_match('~\son[a-z]+\s*=|\sstyle\s*=|<link\b~i', $page) === 0, 'html: no inline script, no handler, no style attribute, no stylesheet before the click');

    // The markup: only through the signed address, rendered after the click.
    $markupUrl = $attrs($page, 'data-shoji-html')[0];
    $r = $call($markupUrl, $html);
    expect(200, $r->code(), 'html route answers');
    expect('text/html', $r->type(), 'as HTML');
    expect('no-store', $r->header('Cache-Control'), 'markup not cached');
    expect('noindex, nofollow', $r->header('X-Robots-Tag'), 'markup not indexed');
    expect('nosniff', $r->header('X-Content-Type-Options'), 'markup nosniff');
    holds(str_contains($r->body(), 'action="https://abc123.sibforms.com/serve/MUIFAbC"'), 'the site snippet\'s form');
    holds(str_contains($r->body(), '<input type="hidden" name="CAM" value="spring-26">'), 'a field the snippet fills from signed params arrives filled');
    expect(404, $call(preg_replace('/s=[0-9a-f]{16}/', 's=0123456789abcdef', $markupUrl), $html)->code(), 'html: wrong signature refused');
    expect(404, $call(str_replace('spring-26', 'stolen', $markupUrl), $html)->code(), 'html: changed parameter refused');
    expect(404, $call(preg_replace('/[?&]s=[0-9a-f]{16}/', '', $markupUrl), $html)->code(), 'html: no signature refused');
    expect(404, $call(str_replace('/html/', '/frame/', $markupUrl), $html)->code(), 'html: signature bound to the kind, no frame for kind html');
    expect(404, $call(str_replace('/html/', '/go/', $markupUrl), $html)->code(), 'html: no go redirect without a link');
    $kirby = $app($html);
    $forged = '/shoji/html/youtube/dQw4w9WgXcQ?s=' . \Shoji\Embed::signer($kirby)->sign('html', 'youtube', 'dQw4w9WgXcQ', '');
    expect(404, $call($forged, $html)->code(), 'html route serves only kind html, even when signed');
    $missing = current(array_filter($attrs($page, 'data-shoji-html'), fn ($u) => str_contains($u, '/missing?')));
    $logged = ini_set('error_log', '/dev/null');
    expect(404, $call($missing, $html)->code(), 'html: a missing snippet is a 404');
    ini_set('error_log', (string) $logged);
    $off = $call($markupUrl, []);
    holds(!($off instanceof Response && $off->code() === 200), 'html: off, the route serves nothing');

    // Scripts and stylesheet: each through its own signed address, the index signed too.
    expect('https://sibforms.com/forms/end-form/build/main.js', $call($scriptList[0], $html)->header('Location'), 'first provider script');
    expect('https://sibforms.com/forms/end-form/build/extra.js', $call($scriptList[1], $html)->header('Location'), 'second provider script');
    expect('https://sibforms.com/forms/end-form/build/sib-styles.css', $call($styleUrl, $html)->header('Location'), 'provider stylesheet');
    expect(404, $call(str_replace('?s=', '?n=1&s=', $scriptList[0]), $html)->code(), 'index not covered by the signature refused');
    expect(404, $call(str_replace('n=1&', 'n=1%0A&', $scriptList[1]), $html)->code(), 'index with a line break refused');
    expect(404, $call(str_replace('/script/', '/style/', $scriptList[0]), $html)->code(), 'script signature is no stylesheet signature');
    $third = '/shoji/script/brevo/newsletter?n=2&s=' . \Shoji\Embed::signer($kirby)->sign('script', 'brevo', 'newsletter', 'n=2');
    expect(404, $call($third, $html)->code(), 'no third script');
    $styleOfVideo = '/shoji/style/youtube/dQw4w9WgXcQ?s=' . \Shoji\Embed::signer($kirby)->sign('style', 'youtube', 'dQw4w9WgXcQ', '');
    expect(404, $call($styleOfVideo, $on + ['shoji.providers' => ['youtube' => ['style' => 'https://www.youtube.com/x.css']]])->code(), 'stylesheets only for kind html');

    // Without JavaScript: the bare markup, or the provider's link if there is one.
    holds(str_contains($page, 'class="shoji-button" href="' . esc($markupUrl) . '"'), 'html: without a link the button leads to the markup itself');
    $linked = $on + ['shoji.providers' => ['brevo' => $brevo + ['link' => 'https://sibforms.com/serve/MUIFAbC']]];
    $go = current(array_filter($attrs($render($linked), 'href'), fn ($u) => str_contains($u, '/shoji/go/brevo/newsletter')));
    expect('https://sibforms.com/serve/MUIFAbC?cam=spring-26', $call($go, $linked)->header('Location'), 'html: link through the go route');
    $offPage = $render(['shoji.providers' => ['brevo' => $brevo + ['link' => 'https://sibforms.com/serve/MUIFAbC']]]);
    holds(str_contains($offPage, 'class="shoji-link" href="https://sibforms.com/serve/MUIFAbC?cam=spring-26"') && !str_contains($offPage, 'sib-form'), 'html off: a plain link, no markup');
    holds(!str_contains($render(['shoji.providers' => ['brevo' => $brevo]]), '<a'), 'html off without a link: nothing');

    // Open mode: the markup right away, provider files through own addresses.
    file_put_contents($tmp . '/site/templates/home.php', "<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'newsletter', 'open' => true]) ?>");
    $open = $render($html);
    holds(str_contains($open, '<div class="shoji-markup"><div class="sib-form">'), 'html open: the markup in the page');
    holds(str_contains($open, '<link rel="stylesheet" href="https://site.example/shoji/style/brevo/newsletter?s='), 'html open: stylesheet through the own address');
    expect(2, preg_match_all('~<script src="https://site\.example/shoji/script/brevo/newsletter\?[^"]+" defer></script>~', $open), 'html open: both scripts through own addresses, deferred');
    holds(!str_contains($open, 'data-shoji '), 'html open: no placeholder');

    // 8. shoji.open: a switch per provider; a call's own open wins in both directions.
    file_put_contents($tmp . '/site/templates/home.php', <<<'PHP'
<!doctype html><html><body>
<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'newsletter', 'params' => ['cam' => 'spring-26']]) ?>
<?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'open' => false]) ?>
<?php snippet('shoji/embed', ['provider' => 'vimeo', 'id' => '76979871', 'open' => true]) ?>
<?php snippet('shoji/embed', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']) ?>
<?= $page->text()->kirbytext() ?>
<?= $page->blocks()->toBlocks() ?>
</body></html>
PHP);
    /** Providers in page order: [opened, placeholders]. */
    $split = function (string $page): array {
        preg_match_all('~<div class="shoji shoji-[a-z]+ shoji-([a-z0-9-]+) shoji-opened"~', $page, $opened);
        preg_match_all('~<div class="shoji shoji-[a-z]+ shoji-([a-z0-9-]+)" data-shoji ~', $page, $closed);
        return [$opened[1], $closed[1]];
    };
    $before = $render($html);
    // Without the option: as before, every call without its own open behind the click.
    expect([['vimeo'], ['brevo', 'digistore', 'youtube', 'youtube', 'vimeo', 'youtube']], $split($before), 'open: without the option only the call with open => true opens');
    expect($before, $render($html + ['shoji.open' => []]), 'open: an empty option changes nothing, byte for byte');
    expect($before, $render($html + ['shoji.open' => null]), 'open: null counts as not set');

    // Switched on for brevo, youtube and digistore.
    $switched = $html + ['shoji.open' => ['brevo' => true, 'youtube' => true, 'digistore' => true]];
    $page = $render($switched);
    expect([['brevo', 'vimeo', 'youtube', 'youtube', 'youtube'], ['digistore', 'vimeo']], $split($page), 'open: switch per provider; open => false keeps a placeholder, open => true opens without a switch; tag and block follow the switch');
    holds(str_contains($page, '<div class="shoji-markup"><div class="sib-form">') && str_contains($page, 'value="spring-26"'), 'open: brevo\'s markup in the page right away, signed params filled');
    holds(str_contains($page, '<link rel="stylesheet" href="https://site.example/shoji/style/brevo/newsletter?s='), 'open: brevo stylesheet through the own address');
    expect(2, preg_match_all('~<script src="https://site\.example/shoji/script/brevo/newsletter\?[^"]+" defer></script>~', $page), 'open: brevo scripts through own addresses');
    expect(4, preg_match_all('~<iframe class="shoji-frame" src="https://site\.example/shoji/frame/(?:youtube|vimeo)/~', $page), 'open: iframes right away, through own addresses');
    holds(str_contains($page, 'src="https://site.example/shoji/frame/youtube/aqz-KE-bpKQ?s='), 'open: the video block opens with the switch');
    holds(str_contains($page, 'data-shoji-frame="https://site.example/shoji/frame/digistore/123456'), 'open: open => false wins over the switch');
    holds(!str_contains($page, '<iframe class="shoji-frame" src="https://site.example/shoji/frame/digistore'), 'open: no digistore iframe');
    expect(1, substr_count($page, '/shoji.js"'), 'open: shoji.js once, for the remaining placeholders');
    holds(preg_match(FOREIGN, preg_replace('~<div class="shoji-markup">.*?</form></div></div></div>~s', '', $page)) === 0, 'open: apart from brevo\'s own markup no provider host in the page');
    $iframes = $attrs($page, 'src');
    $frame = current(array_filter($iframes, fn ($u) => str_contains($u, '/shoji/frame/youtube/dQw4w9WgXcQ')));
    expect('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', $call($frame, $switched)->header('Location'), 'open: the iframe address redirects like after a click');

    // Unknown providers and non-bool values: ignored and logged, the page as without the option.
    $log = tempnam(sys_get_temp_dir(), 'shoji-log');
    $logged = ini_set('error_log', $log);
    expect($before, $render($html + ['shoji.open' => ['nope' => true, 'youtube' => 'yes', 'vimeo' => 1]]), 'open: invalid entries open nothing');
    ini_set('error_log', (string) $logged);
    $lines = (string) file_get_contents($log);
    unlink($log);
    holds(str_contains($lines, "Shoji: ignoring shoji.open for 'nope' (no such provider)"), 'open: unknown provider logged');
    holds(str_contains($lines, 'Shoji: ignoring shoji.open for youtube (value must be true or false)') && str_contains($lines, 'shoji.open for vimeo (value must'), 'open: non-bool values logged');
    expect(1, substr_count($lines, "'nope'"), 'open: logged once per request, not once per embed');

    // Shoji off: the switch opens nothing.
    $off = $render(['shoji.providers' => ['brevo' => $brevo], 'shoji.open' => ['brevo' => true, 'youtube' => true]]);
    holds(!str_contains($off, 'sib-form') && !str_contains($off, 'shoji-opened') && !str_contains($off, '/shoji/'), 'open: off, nothing opens');

    // $site->shojiOpen(): the switch in effect, for a privacy policy that follows it.
    $site = $app($switched)->site();
    expect(true, $site->shojiOpen('brevo'), 'shojiOpen: switched on (own provider)');
    expect(true, $site->shojiOpen('youtube'), 'shojiOpen: switched on (built-in provider)');
    expect(false, $site->shojiOpen('vimeo'), 'shojiOpen: no switch, behind the click (a single call\'s open does not count)');
    expect(false, $site->shojiOpen('nope'), 'shojiOpen: unknown provider');
    expect(true, shoji_embed('brevo', 'newsletter')->open(), 'Embed::open(): the provider\'s switch');
    expect(false, shoji_embed('vimeo', '76979871')->open(), 'Embed::open(): no switch');
    expect(false, $app($html + ['shoji.open' => ['brevo' => false]])->site()->shojiOpen('brevo'), 'shojiOpen: switched off explicitly');
    expect(false, $app($html)->site()->shojiOpen('brevo'), 'shojiOpen: without the option');
    $logged = ini_set('error_log', '/dev/null');
    expect(false, $app($html + ['shoji.open' => ['brevo' => 'yes']])->site()->shojiOpen('brevo'), 'shojiOpen: a non-bool value counts as off');
    ini_set('error_log', (string) $logged);
    expect(false, $app(['shoji.providers' => ['brevo' => $brevo], 'shoji.open' => ['brevo' => true]])->site()->shojiOpen('brevo'), 'shojiOpen: Shoji off, nothing loads right away');

    // 9. An address on a protected provider's host that Shoji cannot embed: never Kirby's own
    //    output (an iframe or <video> that loads with the page), for the tag and the block alike.
    $tagOf = fn (string $url, array $options): string => (string) $app($options)->kirbytext('(video: ' . $url . ')');
    $blockOf = fn (string $url, array $options): string => (string) $app($options)->snippet('blocks/video', [
        'block' => new Block(['id' => 'b', 'type' => 'video', 'content' => ['location' => 'web', 'url' => $url]]),
    ], true);
    /** The fallback box inside whatever wraps it (the tag's figure, the block's figure). */
    $box = function (string $html): string {
        preg_match('~<div class="shoji shoji-fallback[^"]*">.*?</div>~s', $html, $m);
        return $m[0] ?? '';
    };
    $unsupported = [
        // address                                                       => provider
        'https://vimeo.com/showcase/1234567/video/123456789'             => 'vimeo',
        'https://vimeo.com/album/1234567/video/123456789'                => 'vimeo',
        'https://vimeo.com/12345'                                        => 'vimeo',
        'https://player.vimeo.com/video/12345'                           => 'vimeo',
        'https://vimeo.com/123456789/abcdef1234'                         => 'vimeo',
        'https://www.youtube.com/@gasthausmuster'                        => 'youtube',
        'https://www.youtube.com/channel/UCBRboOIpQLe33Rwb0l9dqVw'       => 'youtube',
        'https://m.youtube.com/playlist?list=PL1234567890abcdef'         => 'youtube',
        'https://WWW.YOUTUBE.COM/@gasthausmuster'                        => 'youtube',
        '//www.youtube.com/@gasthausmuster'                              => 'youtube',
        'https://www.digistore24.com/clip.mp4'                           => 'digistore',
        'https://www.digistore24.com/page/impressum'                     => 'digistore',
    ];
    foreach ($unsupported as $url => $provider) {
        $url = (string) $url;
        $link = str_starts_with($url, '//') ? 'https:' . $url : $url;
        $tag = $tagOf($url, $on);
        $block = $blockOf($url, $on);
        foreach (['tag' => $tag, 'block' => $block] as $where => $out) {
            holds(preg_match('~<(?:iframe|video|source|script|img)\b|data-shoji~i', $out) === 0, "fallback $where $url: no iframe, video, script or image, nothing for shoji.js to open");
            holds(str_contains($out, 'class="shoji shoji-fallback shoji-' . $provider . '"'), "fallback $where $url: the placeholder box");
            expect([$link], array_values(array_filter($attrs($out, 'href'))), "fallback $where $url: one plain link, to the pasted address");
            holds(str_contains($out, '>Open on ') && str_contains($out, 'cannot be embedded here. Nothing is loaded from '), "fallback $where $url: link text and note");
        }
        expect($box($tag), $box($block), "fallback $url: tag and block print the same box");
        holds($box($tag) !== '', "fallback $url: box found in the tag");
        // Off: Kirby's own output, unchanged.
        holds(!str_contains($tagOf($url, []), 'shoji-fallback') && !str_contains($blockOf($url, []), 'shoji-fallback'), "fallback $url: off, nothing of it");
    }
    // The German text; the note in the site's words.
    $de = $tagOf('https://vimeo.com/12345', $on + ['shoji.language' => 'de']);
    holds(str_contains($de, '>Auf Vimeo öffnen</a>') && str_contains($de, 'Diese Vimeo-Adresse lässt sich hier nicht einbetten.'), 'fallback: German texts');
    holds(str_contains($tagOf('https://vimeo.com/12345', $on + ['shoji.texts' => ['fallback' => 'Nicht einbettbar: {name}']]), '<p class="shoji-note">Nicht einbettbar: Vimeo</p>'), 'fallback: shoji.texts');
    // Caption of the tag and of the block stay.
    holds(str_contains((string) $app($on)->kirbytext('(video: https://vimeo.com/12345 caption: Tag caption)'), '<figcaption>Tag caption</figcaption>'), 'fallback: tag caption kept');
    holds(str_contains((string) $app($on)->snippet('blocks/video', ['block' => new Block(['id' => 'b', 'type' => 'video', 'content' => ['location' => 'web', 'url' => 'https://vimeo.com/12345', 'caption' => 'Block caption']])], true), '<figcaption>Block caption</figcaption>'), 'fallback: block caption kept');
    // A site's own snippet wins over the shipped one.
    file_put_contents($tmp . '/site/snippets/shoji/fallback.php', '<div class="own-fallback"><?= esc($url) ?></div>');
    holds(str_contains($tagOf('https://vimeo.com/12345', $on), '<div class="own-fallback">https://vimeo.com/12345</div>') && str_contains($blockOf('https://vimeo.com/12345', $on), '<div class="own-fallback">'), 'fallback: a site\'s own snippet takes over');
    unlink($tmp . '/site/snippets/shoji/fallback.php');

    // Everything else stays Kirby's: media files, other hosts, look-alikes of a protected host.
    foreach ([
        'https://example.com/clip.mp4', 'https://cdn.example.com/clip.webm', 'gasthaus-muster.mp4',
        'https://youtube.com.example.org/clip.mp4', 'https://example.com/?u=vimeo.com/12345',
    ] as $url) {
        expect($tagOf($url, []), $tagOf($url, $on), "other $url: the tag as without Shoji");
        expect($blockOf($url, []), $blockOf($url, $on), "other $url: the block as without Shoji");
    }
    holds(str_contains($tagOf('https://example.com/clip.mp4', $on), '<video'), 'other: a video file on another host is still Kirby\'s <video>');

    // A link is only ever an http(s) address; never a javascript: or data: one.
    foreach (['javascript://www.youtube.com/%0aalert(1)', 'data://vimeo.com/x', 'https://vimeo.com/ x"onclick="1'] as $url) {
        $out = $tagOf($url, $on) . $blockOf($url, $on);
        holds(!str_contains($out, 'href='), "no link for $url");
        holds(preg_match('~<(?:iframe|video|source|script)\b|onclick~i', $out) === 0, "nothing loaded for $url");
    }

    // Whatever Kirby would turn into an iframe on a protected host never gets there.
    $kirby = $app($on);
    $iframed = 0;
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/playlist?list=PL1234567890abcdef',
        'https://www.youtube.com/embed/videoseries?list=PL1234567890abcdef', 'https://youtu.be/dQw4w9WgXcQ?list=PL1234567890abcdef',
        'https://www.youtube-nocookie.com/embed/videoseries?list=PL1234567890abcdef', 'HTTPS://WWW.YOUTUBE.COM/playlist?list=PL1234567890abcdef',
        'http://m.youtube.com/playlist?list=PL1234567890abcdef', 'ftp://www.youtube.com/playlist?list=PL1234567890abcdef',
        '//www.youtube.com/playlist?list=PL1234567890abcdef', 'https://music.youtube.com/playlist?list=PL1234567890abcdef',
        'https://vimeo.com/76979871', 'https://vimeo.com/12345', 'https://www.vimeo.com/12345', 'https://player.vimeo.com/video/12345',
        'https://player.vimeo.com/video/76979871?h=abcdef1234',
        'https://vimeo.com/channels/staffpicks/76979871', 'https://vimeo.com/channels/staffpicks/12345',
    ] as $url) {
        try {
            $core = (string) Html::video($url);
        } catch (Throwable) {
            $core = ''; // Kirby refuses the address (a scheme it does not know)
        }
        if (str_contains($core, '<iframe')) {
            $iframed++;
            holds(shoji_video($kirby, $url) !== null, "Kirby would print an iframe for $url; Shoji must not leave it to Kirby");
        }
    }
    holds($iframed >= 12, 'the list holds addresses Kirby really turns into an iframe');

    echo "KirbyTest: $checks checks passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'KirbyTest FAILED: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n");
    $failed = true;
} finally {
    removeTree($tmp);
}
exit(isset($failed) ? 1 : 0);
