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
