<?php

use Kirby\Cms\App;
use Kirby\Cms\Html;
use Kirby\Http\Response;
use Kirby\Text\KirbyTag;

require_once __DIR__ . '/src/I18n.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Signer.php';
require_once __DIR__ . '/src/Providers.php';
require_once __DIR__ . '/src/Embed.php';
require_once __DIR__ . '/src/Http.php';
require_once __DIR__ . '/src/Thumbs.php';

use Shoji\Embed;
use Shoji\I18n;
use Shoji\Options;
use Shoji\Providers;
use Shoji\Thumbs;

/**
 * Shoji (障子, the paper sliding screen: you see the outline, the outside
 * comes in only when you slide it open) — third-party content behind a
 * placeholder until the visitor clicks. Off by default: without
 * `'shoji.active' => true` the snippet prints a plain link, the video block
 * and the (video:) tag behave exactly like Kirby's, and the routes answer 404.
 *
 * Nothing in the page names the provider's host: the preview image, the
 * iframe, the markup of kind 'html', extra scripts and stylesheets and the
 * plain link all point to this site (/shoji/…, signed) and are redirected or
 * served from there.
 *
 * Defaults are passed as fallback values directly at each option call, NOT
 * in an 'options' block (Kirby would file them under sayamaapps.shoji.active
 * otherwise).
 */

/** Reads `shoji.<key>`. Also meant for site snippets with their own markup. */
function shoji_option(string $key, mixed $default = null): mixed
{
    return Options::get(App::instance(), $key, $default);
}

/**
 * The embed for a pasted address (`shoji_embed('https://youtu.be/…')`) or a
 * provider and id (`shoji_embed('youtube', 'dQw4w9WgXcQ')`); null if nothing
 * matches or the id is invalid. For sites that build their own markup.
 */
function shoji_embed(string $urlOrProvider, ?string $id = null, array $params = [], ?string $title = null): ?Embed
{
    $kirby = App::instance();
    return $id === null
        ? Embed::fromUrl($kirby, $urlOrProvider, $params, $title)
        : Embed::make($kirby, $urlOrProvider, $id, $params, $title);
}

/** Language and `shoji.texts` for this request. Snippets call it before printing text. */
function shoji_i18n(): void
{
    $kirby = App::instance();
    I18n::setLanguage(Options::language($kirby));
    I18n::setOverrides(Options::get($kirby, 'texts'));
}

/** Address of assets/shoji.js (Kirby serves plugin assets from /media/plugins/…, with a version hash). */
function shoji_script_url(): ?string
{
    return App::instance()->plugin('sayamaapps/shoji')?->asset('shoji.js')?->url();
}

/**
 * The <script> tag for shoji.js, once per page: the first placeholder
 * prints it, later ones print nothing. External and deferred, so a Content
 * Security Policy needs no 'unsafe-inline'. `'shoji.script' => false` turns
 * it off for sites that bundle the file themselves.
 */
function shoji_script_tag(): string
{
    static $printed = null;
    $printed ??= new WeakMap();
    $kirby = App::instance();
    if (isset($printed[$kirby]) || shoji_option('script', true) === false || ($url = shoji_script_url()) === null) {
        return '';
    }
    $printed[$kirby] = true;
    return '<script src="' . esc($url) . '" defer></script>';
}

/** The route behind /shoji/<kind>/<provider>/<id>. */
function shoji_route(string $kind, string $provider, string $id): Response|false
{
    $kirby = App::instance();
    if (!Options::active($kirby)) {
        return false; // the module does not even reveal itself: Kirby's 404
    }
    $notFound = fn () => new Response('Not found', 'text/plain', 404, ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);

    $signature = get('s');
    $encoded = in_array($kind, ['frame', 'go', 'html', 'thumb'], true) ? get('p', '') : '';
    // The second and later script or stylesheet: their index is signed instead of parameters.
    $index = in_array($kind, ['script', 'style'], true) ? get('n', '0') : '0';
    if (!is_string($signature) || !is_string($encoded) || !is_string($index) || !preg_match('/^(?:0|[1-9]\d?)\z/', $index)) {
        return $notFound();
    }
    $embed = Embed::make($kirby, $provider, $id, Embed::decodeParams($encoded));
    $signed = $index !== '0' ? Embed::encodeIndex((int) $index) : $encoded;
    if ($embed === null || !Embed::signer($kirby)->verify($signature, $kind, $provider, $id, $signed)) {
        return $notFound();
    }

    if ($kind === 'html') {
        // The site's own markup, rendered now (after the click), never cached:
        // parameters may differ on the next click.
        $html = $embed->html();
        if ($html === null) {
            if ($embed->snippet() !== null) {
                error_log('Shoji: the snippet ' . $embed->snippet() . ' of provider ' . $provider . ' is missing or empty');
            }
            return $notFound();
        }
        return new Response($html, 'text/html', 200, [
            'Cache-Control'          => 'no-store',
            'X-Robots-Tag'           => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    if ($kind === 'thumb') {
        $image = $embed->hasThumbnail() ? (new Thumbs(Options::directory($kirby) . '/thumbs'))->get($embed) : null;
        if ($image === null) {
            // A blank image in the right proportions instead of a broken one.
            [$width, $height] = $embed->size() ?? [16, 9];
            return new Response(
                '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"/>',
                'image/svg+xml',
                200,
                ['Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']
            );
        }
        return new Response((string) file_get_contents($image['file']), $image['type'], 200, [
            'Cache-Control'          => 'public, max-age=' . Thumbs::MAX_AGE,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    $target = $embed->target($kind, (int) $index);
    if ($target === null) {
        return $notFound();
    }
    // no-store: parameters (an affiliate id, say) may differ on the next click.
    // No Referrer-Policy here: YouTube refuses to play without a referrer.
    return new Response('', null, 302, [
        'Location'      => $target,
        'Cache-Control' => 'no-store',
        'X-Robots-Tag'  => 'noindex, nofollow',
    ]);
}

/**
 * What the (video:) tag and the video block print for an address while
 * Shoji is on: the placeholder; for an address on a protected provider's
 * host that Shoji cannot embed (Providers::isProtectedHost) the fallback
 * (shoji/fallback: a note and a plain link) — never Kirby's own output, which
 * would load the provider with the page; null for everything else (media
 * files, other hosts), which stays Kirby's. One decision for tag and block.
 */
function shoji_video(App $kirby, string $url): ?string
{
    if (!Options::active($kirby)) {
        return null;
    }
    if ($embed = Embed::fromUrl($kirby, $url)) {
        return (string) $kirby->snippet('shoji/embed', ['embed' => $embed], true);
    }
    return Providers::isProtectedHost($url)
        ? (string) $kirby->snippet('shoji/fallback', ['url' => trim($url)], true)
        : null;
}

/** Kirby's own (video:) tag and video block, for everything Shoji does not take over. */
function shoji_core_tag(string $name): ?array
{
    static $tags = null;
    $tags ??= require App::instance()->root('kirby') . '/config/tags.php';
    return $tags[$name] ?? null;
}

$shojiTranslations = [];
foreach (I18n::LANGUAGES as $shojiLanguage) {
    foreach (I18n::table($shojiLanguage) as $shojiKey => $shojiText) {
        $shojiTranslations[$shojiLanguage]['shoji.' . $shojiKey] = $shojiText;
    }
}
unset($shojiLanguage, $shojiKey, $shojiText);

App::plugin('sayamaapps/shoji', [
    // The visitor-facing strings, also usable as t('shoji.<key>').
    'translations' => $shojiTranslations,

    /**
     * The placeholder (snippets/shoji/embed.php) and the video block.
     * A site with its own markup creates site/snippets/shoji/embed.php —
     * Kirby looks there first.
     */
    'snippets' => [
        'shoji/embed'    => __DIR__ . '/snippets/shoji/embed.php',
        'shoji/fallback' => __DIR__ . '/snippets/shoji/fallback.php',
        'blocks/video'   => __DIR__ . '/snippets/blocks/video.php',
    ],

    /**
     * $site->shojiOpen('brevo'): true if this provider's content loads with
     * the page (Shoji on, `shoji.open` switches it on), false if it waits for
     * the click. For a privacy policy that follows the switch by itself.
     */
    'siteMethods' => [
        'shojiOpen' => function (string $provider): bool {
            return Options::opens($this->kirby(), $provider);
        },
    ],

    'routes' => [
        [
            'pattern' => 'shoji/(thumb|frame|script|style|html|go)/(:any)/(:any)',
            'method'  => 'GET',
            'action'  => fn (string $kind, string $provider, string $id) => shoji_route($kind, $provider, $id),
        ],
    ],

    /**
     * (video: …) — a YouTube or Vimeo address becomes a placeholder, one
     * Shoji cannot embed a note with a plain link (shoji_video()); a file
     * from the page, another host, or Shoji switched off: Kirby's own tag.
     * The attribute list is Kirby's, so existing tags keep parsing.
     */
    'tags' => [
        'video' => [
            'attr' => [
                'autoplay', 'caption', 'controls', 'class', 'disablepictureinpicture', 'height',
                'loop', 'muted', 'playsinline', 'poster', 'preload', 'style', 'width',
            ],
            'html' => function (KirbyTag $tag): string {
                $content = shoji_video($tag->kirby(), (string) $tag->value);
                if ($content === null) {
                    return shoji_core_tag('video')['html']($tag);
                }
                return Html::figure(
                    [$content],
                    $tag->caption,
                    ['class' => $tag->class ?? 'video', 'style' => $tag->style]
                );
            },
        ],
    ],
]);
