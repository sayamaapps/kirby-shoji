# Shoji

Click-to-load embeds and forms for [Kirby CMS](https://getkirby.com).

Shoji keeps YouTube and Vimeo videos, Digistore24 order forms and HTML forms such as Brevo's behind a placeholder until the visitor clicks. The placeholder shows a preview image and a button; the provider's content loads only after the click. Until then, the page contacts only your own server.

## Features

- Placeholders for YouTube, Vimeo, Digistore24 and HTML forms; nothing loads before the click.
- Preview images cached on your server; the visitor's browser never asks the provider for them.
- No cookies, no browser storage, nothing remembered between visits.
- Takes over Kirby's video block and `(video:)` tag automatically.
- Works without JavaScript and under a strict Content Security Policy (no inline script).
- Unstyled `shoji-*` classes; English and German.

## Requirements

- PHP 8.2–8.5 and Kirby 5.
- Kirby's `storage` root (recommended) or a writable `site/storage`, for the secret and the preview cache. If neither is writable, set `shoji.secret`, or placeholders will not open.

## Installation

```sh
composer require sayamaapps/kirby-shoji
```

Composer installs to `site/plugins/shoji/`. Add that path to `.gitignore` and deploy it like `vendor/`.

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
<?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'params' => ['form' => '78901'], 'label' => 'Buy now']) ?>
```

The video block and `(video: https://youtu.be/…)` in the Panel need no change. Recognised addresses are YouTube (watch, `youtu.be`, embed, shorts, live), YouTube playlists, Vimeo (six to twelve digits, `?h=` for unlisted videos) and Digistore24 product and redirect links; parameters passed next to an address win over those it carries. An address on YouTube, Vimeo or Digistore24 that Shoji cannot embed (a showcase or channel link, say) never reaches Kirby's own output: it becomes a note with a plain link, so nothing loads before the click.

### HTML forms (kind `html`)

For a form that only works as markup in the page, define a provider of kind `html`. After the click, the provider's stylesheet loads and your snippet's markup goes into the page; the provider's scripts follow.

```php
// site/config/config.php
'shoji.providers' => [
    'brevo' => [
        'name'    => 'Brevo',
        'company' => 'Brevo',
        'kind'    => 'html',
        'id'      => '/^[a-z-]+$/',
        'snippet' => 'forms/{id}',   // site/snippets/forms/newsletter.php
        'style'   => 'https://sibforms.com/forms/end-form/build/sib-styles.css',
        'script'  => 'https://sibforms.com/forms/end-form/build/main.js',
    ],
],
```

Template: `<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'newsletter']) ?>`. The snippet renders in its own request, so values the page knows go in as `params`. Scripts inside the markup do not run; fill hidden fields, such as a campaign key, on `shoji:inserted`.

### Loading right away (`shoji.open`)

A provider listed in `shoji.open` loads with the page instead of after the click, for example a sign-up form on its own sign-up page:

```php
'shoji.open' => ['brevo' => true],
```

A single call's `open` argument wins over this switch in either direction. Empty the page cache after changing it. `$site->shojiOpen('brevo')` returns `true` when the content loads with the page, so your privacy policy can follow the switch.

### Events

The placeholder dispatches bubbling events with `detail.provider`: `shoji:open` (clicked, nothing loaded yet), `shoji:inserted` (kind `html`: markup in the page, scripts not yet loaded), `shoji:script` (provider scripts loaded, failed or timed out) and `shoji:loaded` (iframe or markup in place).

### Own markup

Create `site/snippets/shoji/embed.php` (Kirby takes it before the package's snippet) and build the placeholder from `shoji_embed()`:

```php
$embed = shoji_embed('https://youtu.be/dQw4w9WgXcQ', null, [], 'Trailer'); // null: unknown address or invalid id
$embed->frameUrl();  // own address of the iframe
$embed->thumbUrl();  // own address of the preview image, or null
$embed->linkUrl();   // own address of the plain link
$embed->open();      // true if shoji.open loads this provider with the page
```

Keep `data-shoji` and the `data-shoji-*` attributes on the container, and print `shoji_script_tag()` once per page. Also: `provider`, `name()`, `company()`, `kind()`, `htmlUrl()`, `scriptUrls()`, `styleUrls()`, `size()`, `allow()`.

## Configuration

All keys are read as `shoji.<name>`, flat or nested. `null` means "not set".

| Option | Default | Description |
|---|---|---|
| `active` | `false` | Master switch; only `true` switches on. Off: plain links, Kirby's own video output, routes answer 404. |
| `privacy` | auto | URL, page id or closure for the privacy link in the note. Auto: first existing page `datenschutz`, `datenschutzerklaerung`, `privacy` or `privacy-policy`. `false`: no link. |
| `language` | site language | `en` or `de`. |
| `texts` | — | Single strings to replace, flat or per language: `['de' => ['note.video' => '…']]`. Keys in `translations/en.php`. |
| `open` | none | Per provider, `name => true` loads its content with the page. See [Loading right away](#loading-right-away-shojiopen). |
| `providers` | built-in | Add a provider, change one field of a built-in one, or remove one with `false`. Fields: see below. |
| `url` | — | Closure `fn (string $url, Shoji\Embed $embed, string $kind): string`, called for each redirect (`frame`, `go`, `script`, `style`). Must return `https`. |
| `script` | `true` | `false`: no script tag; bundle `assets/shoji.js` yourself (`shoji_script_url()`). |
| `secret` | random | Signs Shoji's addresses, 16+ characters. Unset: stored in `storage/shoji/secret`. Set it when several servers share a domain. |

### Providers

A provider is an entry in `shoji.providers`; the built-in ones are `youtube`, `youtube-playlist`, `vimeo` and `digistore`. Fields: `name` and `company` (shown in the note: who loads, who learns the IP address); `kind` (`video`, `form`, `content` or `html`); `id` (regular expression); `match` (finds the id in a pasted address); `urlParams` (the parameters the address carries, or `null` if it cannot carry them); `embed` (iframe address, not for `html`); `link` (plain link, default `embed`); `snippet` (`html` only, e.g. `forms/{id}`); `script` and `style` (`style` for `html` only; an https address or a list of up to ten, loaded in order); `thumbnails` (preview image candidates, or a closure); `size` (`[width, height]`); `allow` (iframe features). Templates fill `{id}`, `{name}` (a parameter) and `{/name}` (a slash and the parameter) and must produce `https` addresses.

## Content Security Policy

Shoji needs no `'unsafe-inline'`. The policy must allow the site itself, the first step of each redirect:

| Directive | Allow |
|---|---|
| `script-src` | `'self'` and each provider script host, e.g. `https://www.digistore24-scripts.com` |
| `frame-src` | `'self'` and each provider frame host, e.g. `https://www.youtube-nocookie.com`. Without `'self'`, nothing opens. |
| `img-src` | `'self'` (preview images) |
| `connect-src` | kind `html`: `'self'`, plus the host the provider's script submits to |
| `style-src` | kind `html`: `'self'` and the provider's stylesheet host |
| `form-action` | kind `html`: the host in the form's `action` |

Test with `Content-Security-Policy-Report-Only` first. YouTube refuses to play without a referrer, so `Referrer-Policy: no-referrer` breaks playback.

## Privacy

- Before the click, the page contacts only your own server; nothing is stored in the browser.
- Preview images are fetched by your server, cached in `storage/shoji/`, and served from there.
- Providers switched on with `shoji.open` load with the page, so your privacy policy must say so.
- A plain link to an address Shoji cannot embed is followed by the visitor, not requested by the page.
- See [PRIVACY.md](docs/PRIVACY.md) (English) and [DATENSCHUTZ.md](docs/DATENSCHUTZ.md) (German text blocks for privacy policies); neither is legal advice.

Tests: `composer test` (PHP) and `node --test tests/*.test.mjs` (`shoji.js`). Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting. Changes: [CHANGELOG.md](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE.md).
