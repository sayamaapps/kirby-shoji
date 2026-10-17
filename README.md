# Shoji

Click-to-load for [Kirby CMS](https://getkirby.com) 5: YouTube, Vimeo, Digistore24 and HTML forms such as Brevo's stay behind a placeholder until the visitor clicks.

*Shoji* (障子) is the Japanese paper sliding screen: you see the outline of what is outside, and the outside only comes in when you slide it open. The plugin shows a preview image, a button and a short note; the video player or the order form loads after the click, not before.

- **No third-party host in the page.** Before the click the HTML names no provider at all, not even in a `data-` attribute. The preview image, the iframe address, the markup of an HTML form, the provider's scripts and stylesheets and the plain link all point to this site (`/shoji/…`) and are served or redirected from there. A checker that reads the HTML (and a visitor's browser) sees only your own server. (The one exception is the plain link of an address Shoji cannot embed: it is a link the visitor follows, not a request; see [below](#addresses-shoji-cannot-embed).)
- **Preview images from your own server.** The server fetches the image once, keeps it in `storage/shoji/`, and serves it. The visitor's browser never asks YouTube or Vimeo.
- **Nothing is remembered.** No cookie, no browser storage: every page asks again. (Version 1 deliberately does not store consent on the visitor's device.)
- **Per provider, if the site wants.** `shoji.open` loads one provider's content with the page (a sign-up form on its own sign-up page, say) while everything else stays behind the click. Off unless configured; see [Loading right away](#loading-right-away-shojiopen).
- **Works without JavaScript.** The button is a real link; without the script it leads to the video on the provider's site.
- **Content Security Policy friendly.** One external, deferred script from Kirby's plugin assets. No inline script, no inline event handler, no style attribute. See [Content Security Policy](#content-security-policy) for the directives.
- **Takes over Kirby's video block and `(video:)` tag.** A YouTube or Vimeo address pasted in the Panel becomes a placeholder by itself. An address on a YouTube, Vimeo or Digistore24 host that Shoji cannot embed gets a note and a plain link, never Kirby's own iframe, so nothing loads before the click either way. See [Addresses Shoji cannot embed](#addresses-shoji-cannot-embed).
- **Providers as a list.** YouTube (via `youtube-nocookie.com`; videos and playlists), Vimeo (with `dnt=1`), Digistore24 (order form, plus its script); add your own in the configuration.
- **HTML forms, too.** A form whose markup the site keeps itself (a Brevo sign-up form with a hidden campaign field, say) comes into the page after the click, with the provider's scripts and stylesheets. See [HTML forms](#html-forms-kind-html).
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

In the Panel, the video block and `(video: https://youtu.be/…)` need nothing: with Shoji on, YouTube and Vimeo addresses become placeholders; an address on a YouTube, Vimeo or Digistore24 host that Shoji does not recognise becomes a note with a plain link ([below](#addresses-shoji-cannot-embed)); videos from the media library and from other hosts stay Kirby's `<video>`.

### Recognised addresses

A pasted address (the snippet's `url`, the video block, `(video:)`, `shoji_embed($url)`) is taken over with what it carries, so the iframe and the plain link lead where the pasted address leads:

| Provider | Address forms | Carried |
|---|---|---|
| `youtube` | `youtube.com/watch?v=…`, `youtu.be/…`, `embed/…`, `shorts/…`, `live/…`, `v/…`, `youtube-nocookie.com/embed/…` | the video id (11 characters; `videoseries` is no video) |
| `youtube-playlist` | `youtube.com/playlist?list=…`, `…/embed/videoseries?list=…` | the list id; the player is `youtube-nocookie.com/embed/videoseries?list=…`. A playlist has no preview image. `watch?v=…&list=…` stays the single video. |
| `vimeo` | `vimeo.com/123456789`, `player.vimeo.com/video/123456789`, `…/channels/…/123456789`, `…/groups/…/videos/123456789` (six to twelve digits) | `?h=…`, the hash of an unlisted video: in the player address, in the plain link (`vimeo.com/123456789/<h>`) and in the oEmbed request for the preview image |
| `digistore` | `digistore24.com/product/123456`, `…/product/123456/78901` (with the order form), `…/redir/123456` | the order form (`form`, path segment), `?aff=` and `?cam=`, in the iframe and in the plain link |

Parameters you pass next to the address win over what it carries: `shoji_embed($url, null, ['aff' => 'mine'])` keeps the address's `cam` and form. What a provider cannot carry is not taken over and not rewritten into something else: an invalid `h`, an over-long `aff`, a Digistore24 `redir/…/partner/campaign` address or a form segment that is no number go to [the fallback](#addresses-shoji-cannot-embed) with the pasted address as the link. So do showcase and album links, Vimeo ids of fewer than six digits and the unlisted `vimeo.com/<id>/<hash>` form; paste the `player.vimeo.com/video/<id>?h=<hash>` address or the `?h=` form to get the placeholder.

`shoji.open` and `$site->shojiOpen()` know the playlist as its own provider, `youtube-playlist`: switching `youtube` on does not open playlists.

### Addresses Shoji cannot embed

Kirby's own video output turns every YouTube or Vimeo address it understands into an `<iframe>` that loads with the page. So that nothing of the kind slips past the click, the video block and the `(video:)` tag never hand an address on one of these hosts to Kirby while Shoji is on: `youtube.com`, `youtu.be`, `youtube-nocookie.com`, `vimeo.com`, `digistore24.com`, and every subdomain of them (`www.`, `m.`, `player.` …). The host decides, not the form of the address: a showcase or album link, a Vimeo id of fewer than six digits, a channel page, a file on one of these hosts. If Shoji recognises the address it prints the placeholder; if not, the same box for the tag and the block:

```html
<div class="shoji shoji-fallback shoji-vimeo">
  <a class="shoji-button" href="https://vimeo.com/showcase/1234567/video/123456789" rel="noopener">Open on Vimeo</a>
  <p class="shoji-note">This Vimeo address cannot be embedded here. Nothing is loaded from Vimeo unless you open the link.</p>
</div>
```

- **No iframe, no `<video>`, no script, no image, no request** to the provider before the visitor follows the link. The link is the pasted address itself (https for a scheme-relative one) and only ever an `http(s)` address on a protected host; any other scheme prints the note without a link. The page names the provider's host in that link, like any other external link on the site.
- The box has no `data-shoji`, so `shoji.js` leaves it alone. Link and note wear the classes of the placeholder's button and note: the CSS for the placeholder styles this box, too. `shoji-fallback` and `shoji-<youtube|vimeo|digistore>` are there for the differences.
- Texts: `fallback` (note) and `open` (link), changeable with `shoji.texts`. A site's own `site/snippets/shoji/fallback.php` overrides the snippet (it gets `$url`).
- Other hosts and media files stay Kirby's. A provider removed with `'shoji.providers' => ['vimeo' => false]` does not bring Kirby's iframe back: its addresses get this box.
- The same decision, for your own code: `Shoji\Providers::isProtectedHost($url)`, and `shoji_video($kirby, $url)`, which returns the placeholder or fallback HTML for an address, or `null` for Kirby's own output.

Not affected: `snippet('shoji/embed', ['url' => …])` prints nothing for an address it does not know, as before.

### Snippet data

| Key | Meaning |
|---|---|
| `url` | A pasted address (YouTube, Vimeo, Digistore24 product). Or: |
| `provider` + `id` | Provider name and id. |
| `params` | Extra parameters for the provider address, e.g. Digistore24's order form (`form`), affiliate (`aff`) and campaign (`cam`), Vimeo's hash (`h`); for kind `html` they reach the site's snippet as `$params`. Names `[A-Za-z][A-Za-z0-9_]*`, values up to 200 characters. They travel signed in Shoji's own address, so nobody can change them in a copied link. With `url`, they win over what the address carries ([above](#recognised-addresses)). |
| `title` | Title of the content: alt text of the preview image, button text, iframe title. |
| `label` | Button text instead of the default. |
| `note` | Note text instead of the default; `false` for none. |
| `class` | Extra classes on the container. |
| `buttonClass` | Extra classes on the button. |
| `frameClass` | Extra classes on the iframe (some providers' scripts look for one). |
| `attrs` | Extra container attributes; only `data-*`, `aria-*` and `id` are taken. |
| `open` | `true`: the content right away, without placeholder — for a site that remembers consent itself. The addresses are still Shoji's own (kind `html`: the snippet's markup is printed as it is, so its form address names the provider). `false`: the placeholder, even if `shoji.open` switches the provider on. Not given (or not a bool): the provider's switch in [`shoji.open`](#loading-right-away-shojiopen). |

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

The container carries the kind (`shoji-video`, `shoji-form`, `shoji-content`, `shoji-html`) and the provider (`shoji-youtube` …). The script tag comes once per page, with the first placeholder. After the click the container gets `shoji-loading`, then an `<iframe class="shoji-frame">`; once the frame has loaded (or after 5 seconds) the placeholder content is removed and the container has `shoji-opened`. Kind `html` gets a `<div class="shoji-markup">` with the site's markup instead of the iframe.

No step holds the content back for more than 5 seconds. The provider's scripts, the stylesheets, the markup and the iframe each get that long; a script that neither loads nor fails (a connection that just hangs) is given up on after 5 seconds and the iframe opens without it, `shoji:script` and `shoji:loaded` fire once, and the container leaves `shoji-loading`. Scripts that arrive later still load, in their order, and run; they open nothing twice. Kind `html` markup that has not arrived after 5 seconds takes the same way out as markup that cannot be fetched: the button's plain link.

A starting point for the CSS. Button and note stay in the flow: when the note needs more room than 16:9 leaves (narrow screen, large text), the box grows instead of laying the note over the button or above the box. Don't add `overflow: hidden` to `.shoji`, the box would no longer grow.

```css
.shoji { position: relative; aspect-ratio: 16 / 9; display: grid; grid-template-rows: minmax(3rem, 1fr) auto; cursor: pointer; background: #111; }
.shoji-thumb, .shoji-frame { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border: 0; }
.shoji-button { position: relative; display: grid; place-items: center; color: #fff; }
.shoji-note { position: relative; margin: 0; padding: .5rem; font-size: .75rem; color: #fff; background: rgb(0 0 0 / .6); }
.shoji-loading > :not(.shoji-frame) { opacity: .4; }
.shoji-form, .shoji-html { aspect-ratio: auto; }   /* a form has no fixed ratio */
.shoji-html .shoji-button, .shoji-html .shoji-note { position: static; }
.shoji-markup:focus { outline: none; }   /* takes the focus from the removed button */
```

### Events

The script dispatches events on the container (they bubble), `detail.provider` names the provider:

| Event | When |
|---|---|
| `shoji:open` | clicked, nothing loaded yet |
| `shoji:inserted` | kind `html`: the markup is in the page, the placeholder content is gone, the provider scripts are not loaded yet — the place to fill hidden fields |
| `shoji:script` | the provider scripts have loaded (or failed, or 5 seconds passed); for an iframe, the iframe comes next |
| `shoji:loaded` | the iframe is there and the placeholder content is gone; kind `html`: the markup and its scripts are there (or 5 seconds passed) |

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
$embed->kind();         // 'video' | 'form' | 'content' | 'html'
$embed->thumbUrl();     // own address of the preview image, or null
$embed->frameUrl();     // own address the iframe loads (redirects to the provider)
$embed->htmlUrl();      // kind 'html': own address of the site's markup, else null
$embed->scriptUrl();    // own address of the first provider script, or null
$embed->scriptUrls();   // own addresses of all provider scripts, in order
$embed->styleUrls();    // kind 'html': own addresses of the provider's stylesheets
$embed->linkUrl();      // own address of the plain link
$embed->allow();        // value for the iframe's allow attribute
$embed->size();         // [1280, 720] or null
$embed->open();         // true if shoji.open switches this provider on (load with the page)
```

Keep `data-shoji` and the `data-shoji-*` attributes on the container so `shoji.js` can open it (`data-shoji-script` and `data-shoji-style` take several own addresses separated by a space), print `shoji_script_tag()` once, and keep the note with the privacy link. `shoji_i18n()` sets the language before you use `t('shoji.<key>')` or `Shoji\I18n::t()`.

## Configuration

All keys are read as `shoji.<name>`, flat or nested. `null` counts as "not set".

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Off: the snippet prints a plain link to the provider, the video block and `(video:)` are Kirby's own, the routes answer Kirby's 404. Only `true` switches on. |
| `privacy` | first existing page of `datenschutz`, `datenschutzerklaerung`, `privacy`, `privacy-policy` | Privacy policy for the link in the note: a URL, a page id, or a closure returning either. `false`: no link. |
| `texts` | — | Replaces single strings: `['de' => ['note.video' => 'Das Video lädt erst nach Deinem Klick von {name}.']]`, or flat for every language. Keys in [`translations/en.php`](translations/en.php). |
| `language` | site language | `en` or `de`. Otherwise the current language of a multi-language site, its default language, the first two letters of Kirby's `locale`, English. |
| `open` | — | Load a provider's content with the page instead of after the click: `['brevo' => true]`. Built-in and own providers; a call's own `open` wins. Unknown names and values other than `true`/`false` are ignored and logged. See [Loading right away](#loading-right-away-shojiopen). |
| `providers` | — | Add providers, change fields of a built-in one, or remove one with `false`. See below. |
| `url` | — | Closure `fn (string $url, Shoji\Embed $embed, string $kind): string`, called when a route redirects (`$kind` = `frame`, `go`, `script` or `style`). Changes the provider address at click time, e.g. adds a campaign from a first-party cookie. Must return an `https` address, otherwise the route answers 404. |
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
| `kind` | `video`, `form`, `content` or `html`: picks button and note texts; `html` loads the site's markup instead of an iframe. |
| `id` | Regular expression for a valid id. Ids must also match `[A-Za-z0-9_-]{1,64}`. |
| `match` | Optional: finds the id (first group) in a pasted address. |
| `urlParams` | Optional: a closure `fn (string $url, array $match): ?array` returning the parameters the pasted address carries besides the id (the built-in Vimeo returns `h`, Digistore24 `form`, `aff`, `cam`). `null`: a form of the address the provider cannot carry; it is not taken over and the next provider is tried. |
| `embed` | The iframe address (not for kind `html`). |
| `snippet` | Kind `html` only: the site's Kirby snippet with the markup, e.g. `forms/{id}` for `site/snippets/forms/<id>.php`. |
| `link` | Optional: the plain link; default `embed` (kind `html`: the bare markup). |
| `script` | Optional: an address or a list of up to ten, loaded in order — before the iframe, or for kind `html` after the markup is in the page. |
| `style` | Optional, kind `html`: a stylesheet address or a list of up to ten, loaded before the markup is shown. |
| `thumbnails` | Optional: preview image candidates (address templates), or a closure `fn (string $id, Closure $fetch, array $params): array` (`$params` are the signed parameters, e.g. Vimeo's `h`). |
| `size` | Optional: `[width, height]` of the preview image. |
| `allow` | Optional: iframe features (`autoplay; fullscreen`). Each gets `*`, because the iframe is redirected from Shoji's own address. |

Address templates know `{id}`, `{name}` (a parameter) and `{/name}` (a slash plus the parameter, or nothing). Parameters no placeholder uses become the query string. Templates must produce `https` addresses.

## HTML forms (kind `html`)

Some forms only work as markup in the page, not as an iframe. Brevo's sign-up form is one: its hosted iframe ignores the page's address, so a hidden field that carries a campaign key (`?cam=…`) never reaches Brevo. Kind `html` keeps such a form behind the same placeholder as an order form:

1. Before the click the page holds the placeholder (button, note naming provider and company, privacy link) and only own addresses. The form's markup is not in the page at all.
2. On the click `shoji.js` fetches the markup from `/shoji/html/<provider>/<id>?p=…&s=…` on your site, which renders **your Kirby snippet** in that request. Meanwhile it loads the provider's stylesheets (through `/shoji/style/…`, at most 5 seconds of waiting). Markup that has not arrived after 5 seconds is treated like markup that cannot be fetched: the button's plain link.
3. The markup goes into the box, the placeholder content goes, the focus moves to the form, and `shoji:inserted` fires: the moment for page scripts to fill hidden fields.
4. Only then the provider's scripts load (through `/shoji/script/…`, one after the other), because they look for their form when they run. `shoji:loaded` follows, after the last script or after 5 seconds.

Why a signed address and not a `<template>` in the page: the markup names the provider (the form's `action`), so a `<template>` would put the provider's host into the HTML before the click, and the page could no longer be checked by reading it. The signed address also means the markup is rendered from your snippet only (the name is fixed in the provider definition, parameters are signed), never from anything a visitor sends.

Scripts inside the markup do **not** run (it is inserted as HTML, and a policy without `'unsafe-inline'` would block them anyway). Provider scripts belong in `script`; settings the provider's script reads from `window` belong in a file of your own that the page loads.

### Example: Brevo with a campaign field

`site/config/config.php`:

```php
'shoji.providers' => [
    'brevo' => [
        'name'    => 'Brevo',
        'company' => 'Brevo',
        'kind'    => 'html',
        'id'      => '/^[a-z-]+$/',
        'snippet' => 'forms/{id}',                                         // site/snippets/forms/newsletter.php
        'style'   => 'https://sibforms.com/forms/end-form/build/sib-styles.css',
        'script'  => 'https://sibforms.com/forms/end-form/build/main.js',
        // optional, without JavaScript: Brevo's hosted form instead of the bare markup
        // 'link' => 'https://abc123.sibforms.com/serve/MUIFAbC…',
    ],
],
```

`site/snippets/forms/newsletter.php` — the form part of Brevo's embed code (*Contacts → Forms → Share → Embed*), with the hidden field added. Leave out Brevo's `<link>`, `<style>` and `<script>` parts; move `style="…"` attributes into your CSS if your policy has no `'unsafe-inline'` for styles:

```php
<div class="sib-form">
  <div id="sib-form-container" class="sib-form-container">
    <div id="error-message" class="sib-form-message-panel">
      <div class="sib-form-message-panel__text sib-form-message-panel__text--center">
        <span class="sib-form-message-panel__inner-text">Die Anmeldung hat nicht geklappt. Bitte versuchen Sie es erneut.</span>
      </div>
    </div>
    <div id="success-message" class="sib-form-message-panel">
      <div class="sib-form-message-panel__text sib-form-message-panel__text--center">
        <span class="sib-form-message-panel__inner-text">Fast geschafft: Bitte bestätigen Sie Ihre Adresse.</span>
      </div>
    </div>
    <div id="sib-container" class="sib-container--large sib-container--vertical">
      <form id="sib-form" method="POST" action="https://abc123.sibforms.com/serve/MUIFAbC…" data-type="subscription">
        <div class="sib-input sib-form-block">
          <div class="form__entry entry_block">
            <div class="form__label-row">
              <label class="entry__label" for="EMAIL" data-required="*">E-Mail-Adresse</label>
              <div class="entry__field">
                <input class="input" type="text" id="EMAIL" name="EMAIL" autocomplete="off" placeholder="E-Mail" data-required="true" required>
              </div>
            </div>
            <label class="entry__error entry__error--primary"></label>
          </div>
        </div>
        <div class="sib-form-block">
          <button class="sib-form-block__button sib-form-block__button-with-loader" form="sib-form" type="submit">Anmelden</button>
        </div>
        <!-- the campaign: filled here from the signed params, or later in the browser (below) -->
        <input type="hidden" name="CAM" value="<?= esc($params['cam'] ?? '') ?>">
        <input type="text" name="email_address_check" value="" class="input--hidden">
        <input type="hidden" name="locale" value="de">
      </form>
    </div>
  </div>
</div>
```

The template:

```php
<?php snippet('shoji/embed', ['provider' => 'brevo', 'id' => 'newsletter']) ?>
```

The snippet runs in the `/shoji/html/…` request, not in the page's: `$page` there is not the page the visitor sees, and the visitor's query string is not there either. Values the page knows when it renders go in as `params` (`['cam' => …]` arrives as `$params['cam']`, signed). A value only the browser knows — a campaign key from the address or from `sessionStorage`, kept by the site's own script — is filled in on `shoji:inserted`. Shoji inserts the markup as the server rendered it, so a field the snippet filled stays filled:

```js
// cam.js, the site's own file (script-src 'self')
function fillCampaign(root) {
  var cam = new URLSearchParams(location.search).get('cam') || sessionStorage.getItem('cam');
  if (!cam) {
    return;
  }
  root.querySelectorAll('input[name="CAM"]').forEach(function (field) {
    if (!field.value) {
      field.value = cam; // a value the server already filled in stays
    }
  });
}
fillCampaign(document);
document.addEventListener('shoji:inserted', function (event) {
  fillCampaign(event.target);
});
```

`cam.js` must be loaded before the click (with `defer` it is, long before anyone clicks). `CAM` must exist as a contact attribute in Brevo.

Without JavaScript the button leads to `link` (Brevo's hosted form, which loses the campaign) or, without one, to `/shoji/html/…` itself: the bare form, which still submits to Brevo. With Shoji switched off the snippet prints the plain `link`, or nothing.

## Loading right away (`shoji.open`)

A page whose very purpose is a form (a newsletter sign-up page, say) loses sign-ups to an extra click before the form. `shoji.open` lets the site decide per provider:

```php
'shoji.open' => [
    'brevo' => true,    // the sign-up form loads with the page
    // YouTube, Vimeo, Digistore24 …: not listed, so behind the click as before
],
```

- **Per provider**, built-in or own (`shoji.providers`). There is no switch for all providers at once. Without the option nothing changes.
- **A single call wins** in both directions: `snippet('shoji/embed', [… 'open' => false])` keeps the placeholder although the provider is switched on; `'open' => true` opens without a switch, as before. The video block and `(video:)` have no `open` of their own and follow the switch.
- **Typos open nothing.** An unknown provider name or a value other than `true`/`false` is ignored and written to PHP's error log (`Shoji: ignoring shoji.open for …`).
- **Shoji off** (`shoji.active` not `true`): the switch does nothing; the snippet prints its plain link.

What "right away" means — the same output as a call with `open => true`:

| Kind | In the page | The browser contacts the provider |
|---|---|---|
| `video`, `form`, `content` | `<iframe src="/shoji/frame/…" loading="lazy">`, the provider's `script` as `<script src="/shoji/script/…" defer>` | when the iframe comes near the visible area (on the first screen: with the page), redirected from the site; scripts with the page |
| `html` | the snippet's markup (its form `action` names the provider's host), stylesheets as `<link href="/shoji/style/…">`, scripts as `<script src="/shoji/script/…" defer>` | with the page |

No preview image, no button and no note: the page itself no longer tells the visitor before anything loads, so the privacy policy has to. A checker that reads the HTML sees an iframe or script pointing to the site; for kind `html` it sees the provider's host in the form.

### The privacy policy follows the switch

`$site->shojiOpen('brevo')` is `true` if the provider's content loads with the page (Shoji on and `shoji.open` switches it on), `false` if it waits for the click. A single call's `open` does not count. Use it to pick the matching text, so a change in `config.php` changes the privacy policy as well. The texts come from [DATENSCHUTZ.md](DATENSCHUTZ.md) (both versions per block); the wording is the site's.

`site/snippets/privacy/brevo.php`:

```php
<?php if ($site->shojiOpen('brevo')) : ?>
<p>Das Anmeldeformular von Brevo wird beim Aufruf der Seite geladen. […]</p>
<?php else : ?>
<p>Das Anmeldeformular von Brevo wird nicht beim Aufruf der Seite geladen, sondern erst nach Ihrem Klick. […]</p>
<?php endif ?>
```

In the privacy page's template `<?php snippet('privacy/brevo') ?>`, or as a KirbyTag in its text, `(privacy: brevo)` on a line of its own:

```php
// site/plugins/privacy/index.php
Kirby::plugin('mysite/privacy', [
    'tags' => [
        'privacy' => [
            'html' => fn (Kirby\Text\KirbyTag $tag): string => preg_match('/^[a-z0-9-]+$/', $tag->value)
                ? snippet('privacy/' . $tag->value, [], true)
                : '',
        ],
    ],
]);
```

With Kirby's page cache on, empty it after changing `shoji.open`: a cached privacy page does not notice a change in `config.php`.

## Content Security Policy

Shoji needs no `'unsafe-inline'`, but the policy must allow the first step of each redirect, which is the site itself:

| Directive | Needs | Why |
|---|---|---|
| `script-src` | `'self'` plus each provider script host (Digistore24: `https://www.digistore24-scripts.com`; Brevo: `https://sibforms.com`) | `shoji.js` comes from `/media/plugins/…`; `/shoji/script/…` redirects to the provider script, and the policy checks the target, too |
| `frame-src` | `'self'` plus each provider frame host (`https://www.youtube-nocookie.com`, `https://player.vimeo.com`, `https://www.digistore24.com` …) | the iframe starts at `/shoji/frame/…` on your site and is redirected; **without `'self'` nothing opens** |
| `img-src` | `'self'` | preview images come from `/shoji/thumb/…` |
| `connect-src` | kind `html`: `'self'` (plus the host the provider's script sends the form to; Brevo: your form's `https://….sibforms.com`) | `shoji.js` fetches the markup from `/shoji/html/…`; Brevo's script submits the form itself |
| `style-src` | kind `html`: `'self'` plus each provider stylesheet host (Brevo: `https://sibforms.com`) | `/shoji/style/…` redirects to the stylesheet |
| `form-action` | kind `html`: the host in the form's `action` (Brevo: `https://….sibforms.com`) | the form submits there; also without the provider's script |

A policy with `default-src 'self'` and no `connect-src` already allows the fetch. Whatever the provider's files load in turn (fonts, a captcha, images) needs its own entries; Brevo's form with reCAPTCHA needs Google's hosts as well.

Test with `Content-Security-Policy-Report-Only` first: a click on every kind of placeholder must not produce a report.

## How it works

| Address | Answers |
|---|---|
| `/shoji/thumb/<provider>/<id>?p=…&s=…` | The preview image from the cache, fetched from the provider when missing or older than 30 days. A failed fetch is remembered for a day; meanwhile (and when nothing usable comes back) a blank SVG in the right proportions. |
| `/shoji/frame/<provider>/<id>?p=…&s=…` | 302 to the provider's embed address. The iframe loads this. |
| `/shoji/script/<provider>/<id>?n=…&s=…` | 302 to the provider script; `n` (1, 2 …) for the second and later script, signed like the rest. |
| `/shoji/style/<provider>/<id>?n=…&s=…` | Kind `html`: 302 to the provider stylesheet. |
| `/shoji/html/<provider>/<id>?p=…&s=…` | Kind `html`: the site's snippet, rendered now, `text/html`, `no-store`, `noindex`. A missing or empty snippet is a 404. |
| `/shoji/go/<provider>/<id>?p=…&s=…` | 302 to the provider's page (the plain link). |

`s` is an HMAC over kind, provider, id and parameters (or the script's index) with the site's secret (no parameters, no `p`: such an address is the same as in 1.2): only addresses the site rendered work, and the image route cannot be used to make the server fetch arbitrary images. Redirects are `no-store` and `noindex`. Images are checked by their first bytes (JPEG, PNG, WebP), at most 5 MB, and fetched without any visitor data.

The iframe's `allow` attribute lists every feature with `*`: the frame starts at Shoji's own address and is redirected, so the default allowlist (the frame's own origin) would not reach the provider. The redirect keeps the page as referrer; YouTube refuses to play without one.

Not covered: content a template embeds itself (`<iframe>` in a template, Kirby's `video()` helper, raw HTML in a text field), and addresses on other hosts, which stay Kirby's. Check the page with a tool that reads the HTML, or with the browser's network panel.

## Privacy

See [PRIVACY.md](PRIVACY.md) for what happens before and after the click (and with the page, for providers switched on with `shoji.open`), and [DATENSCHUTZ.md](DATENSCHUTZ.md) for the German technical text blocks for privacy policies, each in a version "after the click" and "right away". They describe the implementation and are not legal advice.

## Development

```sh
composer install --ignore-platform-req=ext-gd   # Kirby is a dev dependency used as a test fixture
composer test                                   # or: sh tests/run.sh
```

The tests are plain PHP scripts (exit code 0 = pass). `KirbyTest.php` renders a page with every way content gets in and fails if a provider host appears in the HTML before the click; no test needs a network. `node --test tests/*.test.mjs` checks `shoji.js` against a small fake DOM: it opens only Shoji's own `/shoji/frame|html|script|style/…` addresses on the same host, so injected `data-shoji` attributes cannot load a `javascript:` frame, foreign markup or a foreign script; and kind `html` puts the markup in before `shoji:inserted` and the provider's scripts.

## Maintenance

This module is open source under the MIT license and is maintained for the author's own projects. Issues and pull requests are welcome, but there is no support promise and no release schedule. Report security problems privately through GitHub's private vulnerability reporting (the "Report a vulnerability" button on the Security tab).

## License

MIT, see [LICENSE](LICENSE).
