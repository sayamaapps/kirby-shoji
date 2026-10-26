# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change that makes a page contact a provider earlier** (before the
  click, or without one) is a **major** version, whatever the code change.
- **A change to a built-in provider's addresses** (embed, link, script,
  preview image) is at least a minor version and listed here.
- A change to the privacy text (`DATENSCHUTZ.md` / `PRIVACY.md`) is marked
  **[privacy text]**. Every site using the plugin must then review its privacy
  policy.

## [Unreleased]

### Fixed

- **A corrected Vimeo hash shows its preview image at once.** The memory of a
  failed preview fetch was kept per video id only, so a link corrected to the
  right `h` parameter stayed without an image for up to a day. The memory now
  belongs to the address parameters it was made with; the same parameters are
  still not asked again within a day.

## [1.3.0] — 2026-10-17

### Fixed

- **An address on a YouTube, Vimeo or Digistore24 host that Shoji did not
  recognise no longer reaches Kirby's own video output.** The video block and
  the `(video:)` tag handed such an address to Kirby, which printed an iframe
  (YouTube playlists, Vimeo showcase and album links, Vimeo ids of fewer than
  six digits) or a `<video>` that loaded the provider with the page, before
  any click. They now print a note and a plain link to the address (new
  snippet `shoji/fallback`), the same for tag and block. The hosts:
  `youtube.com`, `youtu.be`, `youtube-nocookie.com`, `vimeo.com`,
  `digistore24.com` and their subdomains (`Providers::isProtectedHost()`).
  Other hosts and media files stay Kirby's. Only with `shoji.active`.
- **[privacy text]** README, PRIVACY.md and the operator notes in
  DATENSCHUTZ.md: addresses Shoji cannot embed. The text blocks are
  unchanged; the page now does what they already said.
- **A pasted address arrives complete** (`Embed::fromUrl()` took only the id).
  Vimeo's `h` (hash of an unlisted video) now reaches the player address, the
  plain link (`vimeo.com/<id>/<h>`) and the oEmbed request for the preview
  image; Digistore24's order form (`…/product/<id>/<form>`) and `aff`/`cam` reach
  the iframe and the plain link `/shoji/go`; a YouTube
  `embed/videoseries?list=…` address is no longer the video "videoseries"
  but a playlist. Parameters given explicitly next to the address win over
  what the address carries. All of it is covered by the signature. What a
  provider cannot carry (an invalid `h`, an over-long `aff`, a Digistore24
  `redir/<id>/<affiliate>/<campaign>` path) is not taken over and lands in
  the fallback above, with the pasted address as the link.
- **[privacy text]** PRIVACY.md and the operator notes in DATENSCHUTZ.md: the
  preview image request of an unlisted Vimeo video names its hash; a
  playlist has no preview image.

- **A helper script that never answers no longer holds the content back.**
  `loadScripts()` waited for `load`/`error` of each provider script without a
  time limit, so a connection that just hung kept the iframe from opening
  and the container on `shoji-loading` for as long as the stall lasted.
  Scripts, stylesheets, markup and iframe now each get 5 seconds: after that
  the iframe opens (kind `html`: `shoji:loaded` fires) without the script,
  `shoji:script` and `shoji:loaded` come once, and the state resolves. A
  script that arrives late still loads and runs, in its order, and opens
  nothing twice. The markup fetch of kind `html` has the same limit; markup
  that has not arrived by then takes the way out of a failed fetch, the
  button's plain link.

- **Behind Sudare (preview protection) preview images are no longer cacheable
  by a shared cache.** `/shoji/thumb/…` answered with `public, max-age=…`
  (the blank SVG with `public, max-age=3600`, the image with 30 days), which
  let a shared HTTP cache in front of a protected preview store an image
  fetched with the preview credentials and serve it to anonymous requests.
  While `\Sudare\Sudare::active()` is true both answers now send
  `private, no-store`; nothing else about them changes. Without Sudare the
  headers stay as they were. No new dependency, no change to a provider
  address.

### Added

- Provider `youtube-playlist` (`youtube.com/playlist?list=…`,
  `embed/videoseries?list=…`): the player `www.youtube-nocookie.com/embed/videoseries?list=…`
  and the plain link `www.youtube.com/playlist?list=…`, behind the same
  placeholder (no preview image). Own provider name: `shoji.open` for
  `youtube` does not open playlists.
- Provider field `urlParams`: a closure for the parameters a pasted address
  carries besides the id (null: not taken over). The built-in Vimeo and
  Digistore24 use it.
- `shoji_video($kirby, $url)`: the placeholder, the fallback or `null`
  (Kirby's own output) for an address, shared by the tag and the block.
- Text `fallback` (en, de), usable with `shoji.texts`; the link uses `open`.

### Changed

- Built-in provider addresses (a minor change by the rules above): Vimeo's
  link is `https://vimeo.com/{id}{/h}` (without a hash as before); the Vimeo
  preview closure takes a third argument, `array $params` (closures with two
  parameters keep working).
- The preview image address `/shoji/thumb/…` carries `p=` (signed) when the
  embed has parameters, like the frame and link addresses; without
  parameters it is the address it was. Pages cached with the old form keep
  working.
- A YouTube `videoseries` is no longer a valid `youtube` video id.

## [1.2.0] — 2026-10-13

### Added

- Option `shoji.open`: a switch per provider, e.g. `['brevo' => true]`. A
  provider switched on loads with the page, without placeholder, exactly as
  a call with `open => true` (still through Shoji's own addresses). For
  built-in and own providers; there is no switch for all at once. Unknown
  provider names and values other than `true`/`false` are ignored and logged
  (`Shoji: ignoring shoji.open for …`), once per request.
- A call's own `open` wins in both directions: `false` keeps the placeholder
  although the provider is switched on, `true` opens without a switch (as
  before). The video block and `(video:)` have no `open` and follow the
  switch.
- `$site->shojiOpen('<provider>')`: whether the provider loads with the page
  (Shoji on and switched on), so a privacy policy can pick its text by
  itself; README example as a snippet and a KirbyTag. `Embed::open()` for
  sites with their own markup.
- **[privacy text]** DATENSCHUTZ.md: a version "sofort geladen" of every
  text block (videos and other iframe content, forms in an iframe, HTML
  forms): what reaches the provider when the page loads, the legal basis left
  to the operator; operator notes on `shoji.open` and on checking a page.
  PRIVACY.md: the same in English. Existing blocks unchanged apart from a
  pointer to the new versions and one sentence in the note on `open`. Sites
  that switch no provider on need not change their privacy policy.

### Changed

- User agent of the preview image fetch: `kirby-shoji/1.2`.

### Unchanged

- Without `shoji.open` every page is the same byte for byte (tested). No
  page contacts a provider earlier unless the site switches that provider on
  itself, as `open => true` could since 1.0; hence a minor version.
- A value of `open` that is not a bool still means "not given" — with a
  switch, the switch now decides.
- Built-in providers and their addresses; `shoji.js`.

## [1.1.0] — 2026-10-13

### Added

- Kind `html` for forms that only work as markup in the page (a Brevo
  sign-up form with a hidden campaign field, say). The provider names a
  snippet of the site (`snippet`, e.g. `forms/{id}`); the placeholder is the
  one of an order form. After the click `shoji.js` fetches the markup from the
  new signed address `/shoji/html/…` (rendered from the snippet in that
  request, signed `params` as `$params`), loads the provider's stylesheets
  first, inserts the markup, fires the new event `shoji:inserted` (for page
  scripts that fill hidden fields), and then loads the provider's scripts.
  Before the click the page names no provider host and holds no part of the
  form.
- Provider field `style` (kind `html`) and the route `/shoji/style/…`;
  `script` takes a list as well, loaded in order. The second and later file
  carry a signed index (`n=1` …); the first keeps its 1.0 address.
- `Embed::htmlUrl()`, `scriptUrls()`, `styleUrls()`, `snippet()`, `html()`;
  `data-shoji-script` takes several own addresses separated by a space.
- Texts `note.html` (English, German).
- **[privacy text]** DATENSCHUTZ.md: text block for HTML forms (example:
  Brevo) and a note for operators; PRIVACY.md: the same in English. Existing
  text unchanged apart from a pointer to the new block. Sites that use no
  kind `html` need not change their privacy policy.

### Unchanged

- Built-in providers and their addresses; the markup of every 1.0
  placeholder (checked byte for byte apart from the asset version).

## [1.0.0] — 2026-10-10

### Added

- Placeholder snippet `shoji/embed`: preview image from the site's own
  server, a button that is a real link without JavaScript, a note naming
  provider and company, a link to the privacy policy. The third-party content
  loads only after the click.
- Providers `youtube` (youtube-nocookie.com), `vimeo` (dnt=1) and `digistore`
  (Digistore24 order form plus its script); more via `shoji.providers`.
- Own, signed addresses `/shoji/thumb|frame|script|go/…`: the page names no
  provider host before the click. Preview images cached in
  `storage/shoji/thumbs/` for 30 days, misses for a day.
- Kirby's video block and `(video:)` tag take YouTube and Vimeo addresses
  over; everything else stays Kirby's.
- `shoji.url` hook to change the provider address at click time, `params`
  for values a template knows at render time (both signed or server-side).
- `shoji.js` as a plugin asset: no inline script, no cookies, no browser
  storage; events `shoji:open`, `shoji:script`, `shoji:loaded`.
- English and German, single strings replaceable with `shoji.texts`.
- **[privacy text]** PRIVACY.md and DATENSCHUTZ.md.
