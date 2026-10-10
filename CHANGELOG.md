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
