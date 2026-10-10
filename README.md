# Shoji

Click-to-load for [Kirby CMS](https://getkirby.com) 5: YouTube, Vimeo and Digistore24 stay behind a placeholder until the visitor clicks.

*Shoji* (障子) is the Japanese paper sliding screen: you see the outline of what is outside, and the outside only comes in when you slide it open. The plugin shows a preview image, a button and a short note; the video player or the order form loads after the click, not before.

- **No third-party host in the page.** Before the click the HTML names no provider at all, not even in a `data-` attribute. The preview image, the iframe address, an extra provider script and the plain link all point to this site (`/shoji/…`) and are served or redirected from there. A checker that reads the HTML (and a visitor's browser) sees only your own server.
- **Preview images from your own server.** The server fetches the image once, keeps it in `storage/shoji/`, and serves it. The visitor's browser never asks YouTube or Vimeo.
- **Nothing is remembered.** No cookie, no browser storage: every page asks again. (Version 1 deliberately does not store consent on the visitor's device.)
- **Works without JavaScript.** The button is a real link; without the script it leads to the video on the provider's site.
- **Content Security Policy friendly.** One external, deferred script from Kirby's plugin assets. No inline script, no inline event handler, no style attribute.
- **Takes over Kirby's video block and `(video:)` tag.** A YouTube or Vimeo address pasted in the Panel becomes a placeholder by itself.
- **Providers as a list.** YouTube (via `youtube-nocookie.com`), Vimeo (with `dnt=1`), Digistore24 (order form, plus its script); add your own in the configuration.
- **Unstyled.** Classes `shoji-*` only; the look belongs to the site.
- **English and German.** Texts follow the site's language and can be changed one by one (du/Sie).

## Requirements

- PHP 8.2–8.5
- Kirby 5
- A `storage` root (recommended) or a writable `site/storage`, for the secret and the image cache

## Installation

```sh
composer require sayamaapps/kirby-shoji
```

Composer installs the plugin into `site/plugins/shoji/` (via `getkirby/composer-installer`). Add `/site/plugins/shoji/` to `.gitignore` and make sure your deploy ships it, just as it ships `kirby/` and `vendor/`.

Until the package is on Packagist, add the repository first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/simsimmal/kirby-shoji" }]
```

## Usage

Everything is off by default. Switch it on in `site/config/config.php`:

```php
return [
    'shoji.active' => true,
];
```

In a template:

```php
<?php snippet('shoji/embed', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Album trailer']) ?>
<?php snippet('shoji/embed', ['provider' => 'vimeo', 'id' => '76979871']) ?>
<?php snippet('shoji/embed', [
    'provider' => 'digistore',
    'id'       => '123456',
    'params'   => ['form' => '78901', 'aff' => $affiliateId],
    'label'    => 'Buy now',
]) ?>
```

In the Panel, the video block and `(video: https://youtu.be/…)` need nothing: with Shoji on, YouTube and Vimeo addresses become placeholders; videos from the media library stay Kirby's `<video>`.

### Snippet data

| Key | Meaning |
|---|---|
| `url` | A pasted address (YouTube, Vimeo, Digistore24 product). Or: |
| `provider` + `id` | Provider name and id. |
| `params` | Extra parameters for the provider address, e.g. Digistore24's order form (`form`) and affiliate (`aff`). Names `[A-Za-z][A-Za-z0-9_]*`, values up to 200 characters. They travel signed in Shoji's own address, so nobody can change them in a copied link. |
| `title` | Title of the content: alt text of the preview image, button text, iframe title. |
| `label` | Button text instead of the default. |
| `note` | Note text instead of the default; `false` for none. |
| `class` | Extra classes on the container. |
| `frameClass` | Extra classes on the iframe (some providers' scripts look for one). |
| `attrs` | Extra container attributes; only `data-*`, `aria-*` and `id` are taken. |
| `open` | `true`: the content right away, without placeholder — for a site that remembers consent itself. The addresses are still Shoji's own. |

An invalid id or an unknown address prints nothing.

### Markup

```html
<div class="shoji shoji-video shoji-youtube" data-shoji data-shoji-provider="youtube"
     data-shoji-frame="https://example.com/shoji/frame/youtube/dQw4w9WgXcQ?s=…" …>
  <img class="shoji-thumb" src="https://example.com/shoji/thumb/youtube/dQw4w9WgXcQ?s=…" alt="Preview image: Album trailer" width="1280" height="720" loading="lazy">
  <a class="shoji-button" href="https://example.com/shoji/go/youtube/dQw4w9WgXcQ?s=…" rel="nofollow">Play video: Album trailer</a>
  <p class="shoji-note">The video is loaded from YouTube only after you click. Google then learns your IP address. <a class="shoji-privacy" href="…/datenschutz">Privacy policy</a></p>
</div>
<script src="https://example.com/media/plugins/sayamaapps/shoji/…/shoji.js" defer></script>
```

The container carries the kind (`shoji-video`, `shoji-form`, `shoji-content`) and the provider (`shoji-youtube` …). The script tag comes once per page, with the first placeholder. After the click the container gets `shoji-loading`, then an `<iframe class="shoji-frame">`; once the frame has loaded (or after 5 seconds) the placeholder content is removed and the container has `shoji-opened`.

A starting point for the CSS:

```css
.shoji { position: relative; aspect-ratio: 16 / 9; cursor: pointer; background: #111; }
.shoji-thumb, .shoji-frame { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border: 0; }
.shoji-button { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; }
.shoji-note { position: absolute; inset: auto 0 0; margin: 0; padding: .5rem; font-size: .75rem; color: #fff; background: rgb(0 0 0 / .6); }
.shoji-loading > :not(.shoji-frame) { opacity: .4; }
.shoji-form { aspect-ratio: auto; }   /* an order form has no fixed ratio */
```

### Events

The script dispatches events on the container (they bubble), `detail.provider` names the provider:

| Event | When |
|---|---|
| `shoji:open` | clicked, nothing loaded yet |
| `shoji:script` | the provider script has loaded (or failed); the iframe comes next |
| `shoji:loaded` | the iframe is there and the placeholder content is gone |

```js
document.addEventListener('shoji:open', (event) => {
  if (event.detail.provider === 'digistore') {
    fetch('/my-own-analytics', { method: 'POST' });
  }
});
```

### Your own markup

Create `site/snippets/shoji/embed.php` (Kirby takes it before the package's snippet), or any snippet, and build it from the embed object:

```php
$embed = shoji_embed('https://youtu.be/dQw4w9WgXcQ', null, [], 'Trailer');  // or shoji_embed('youtube', 'dQw4w9WgXcQ')
// null = unknown address or invalid id
$embed->provider;       // 'youtube'
$embed->name();         // 'YouTube'      $embed->company();  // 'Google'
$embed->kind();         // 'video' | 'form' | 'content'
$embed->thumbUrl();     // own address of the preview image, or null
$embed->frameUrl();     // own address the iframe loads (redirects to the provider)
$embed->scriptUrl();    // own address of the provider script, or null
$embed->linkUrl();      // own address of the plain link
$embed->allow();        // value for the iframe's allow attribute
$embed->size();         // [1280, 720] or null
```

Keep `data-shoji` and the `data-shoji-*` attributes on the container so `shoji.js` can open it, print `shoji_script_tag()` once, and keep the note with the privacy link. `shoji_i18n()` sets the language before you use `t('shoji.<key>')` or `Shoji\I18n::t()`.

## Configuration

All keys are read as `shoji.<name>`, flat or nested. `null` counts as "not set".

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Off: the snippet prints a plain link to the provider, the video block and `(video:)` are Kirby's own, the routes answer Kirby's 404. Only `true` switches on. |
| `privacy` | first existing page of `datenschutz`, `datenschutzerklaerung`, `privacy`, `privacy-policy` | Privacy policy for the link in the note: a URL, a page id, or a closure returning either. `false`: no link. |
| `texts` | — | Replaces single strings: `['de' => ['note.video' => 'Das Video lädt erst nach Deinem Klick von {name}.']]`, or flat for every language. Keys in [`translations/en.php`](translations/en.php). |
| `language` | site language | `en` or `de`. Otherwise the current language of a multi-language site, its default language, the first two letters of Kirby's `locale`, English. |
| `providers` | — | Add providers, change fields of a built-in one, or remove one with `false`. See below. |
| `url` | — | Closure `fn (string $url, Shoji\Embed $embed, string $kind): string`, called when a route redirects (`$kind` = `frame`, `go` or `script`). Changes the provider address at click time, e.g. adds a campaign from a first-party cookie. Must return an `https` address, otherwise the route answers 404. |
| `script` | `true` | `false`: the snippet prints no script tag; the site bundles `assets/shoji.js` itself (`shoji_script_url()` has the address). |
| `secret` | random, in `storage/shoji/secret` | Signs Shoji's addresses. Set it (16+ characters) when several servers share a domain. |

### Providers

A provider is a list entry; built-in ones are in [`src/Providers.php`](src/Providers.php):

```php
'shoji.providers' => [
    'vimeo'   => false,                                                   // remove
    'youtube' => ['embed' => 'https://www.youtube-nocookie.com/embed/{id}?autoplay=1&rel=0'],  // change one field
    'spotify' => [                                                        // add
        'name'    => 'Spotify',
        'company' => 'Spotify',
        'kind'    => 'content',
        'id'      => '/^[A-Za-z0-9]{22}$/',
        'match'   => '~open\.spotify\.com/track/([A-Za-z0-9]{22})~',
        'embed'   => 'https://open.spotify.com/embed/track/{id}',
        'link'    => 'https://open.spotify.com/track/{id}',
        'allow'   => 'encrypted-media',
    ],
],
```

| Field | Meaning |
|---|---|
| `name`, `company` | Shown in the note: who loads, who learns the IP address. |
| `kind` | `video`, `form` or `content`: picks button and note texts. |
| `id` | Regular expression for a valid id. Ids must also match `[A-Za-z0-9_-]{1,64}`. |
| `match` | Optional: finds the id (first group) in a pasted address. |
| `embed` | The iframe address. |
| `link` | Optional: the plain link; default `embed`. |
| `script` | Optional: a script loaded before the iframe. |
| `thumbnails` | Optional: preview image candidates (address templates), or a closure `fn (string $id, Closure $fetch): array`. |
| `size` | Optional: `[width, height]` of the preview image. |
| `allow` | Optional: iframe features (`autoplay; fullscreen`). Each gets `*`, because the iframe is redirected from Shoji's own address. |

Address templates know `{id}`, `{name}` (a parameter) and `{/name}` (a slash plus the parameter, or nothing). Parameters no placeholder uses become the query string. Templates must produce `https` addresses.

## How it works

| Address | Answers |
|---|---|
| `/shoji/thumb/<provider>/<id>?s=…` | The preview image from the cache, fetched from the provider when missing or older than 30 days. A failed fetch is remembered for a day; meanwhile (and when nothing usable comes back) a blank SVG in the right proportions. |
| `/shoji/frame/<provider>/<id>?p=…&s=…` | 302 to the provider's embed address. The iframe loads this. |
| `/shoji/script/<provider>/<id>?s=…` | 302 to the provider script. |
| `/shoji/go/<provider>/<id>?p=…&s=…` | 302 to the provider's page (the plain link). |

`s` is an HMAC over kind, provider, id and parameters with the site's secret: only addresses the site rendered work, and the image route cannot be used to make the server fetch arbitrary images. Redirects are `no-store` and `noindex`. Images are checked by their first bytes (JPEG, PNG, WebP), at most 5 MB, and fetched without any visitor data.

The iframe's `allow` attribute lists every feature with `*`: the frame starts at Shoji's own address and is redirected, so the default allowlist (the frame's own origin) would not reach the provider. The redirect keeps the page as referrer; YouTube refuses to play without one.

Not covered: content a template embeds itself (`<iframe>` in a template, Kirby's `video()` helper, raw HTML in a text field). Check the page with a tool that reads the HTML, or with the browser's network panel.

## Privacy

See [PRIVACY.md](PRIVACY.md) for what happens before and after the click, and [DATENSCHUTZ.md](DATENSCHUTZ.md) for the German technical text block for privacy policies. They describe the implementation and are not legal advice.

## Development

```sh
composer install --ignore-platform-req=ext-gd   # Kirby is a dev dependency used as a test fixture
composer test                                   # or: sh tests/run.sh
```

The tests are plain PHP scripts (exit code 0 = pass). `KirbyTest.php` renders a page with every way content gets in and fails if a provider host appears in the HTML before the click; no test needs a network. `node --test tests/*.test.mjs` checks `shoji.js` against a small fake DOM: it opens only Shoji's own `/shoji/frame|script/…` addresses on the same host, so injected `data-shoji` attributes cannot load a `javascript:` frame or a foreign script.

## License

MIT, see [LICENSE](LICENSE).
