# Shoji: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German text
block for privacy policies, which is the authoritative version. Both describe
the implementation. They are not legal advice. Each operator has to determine
the legal basis and the wording for their own site.

## Before the click

The visitor's browser talks only to the site. The page shows a placeholder
with a preview image, a button and a note. The preview image comes from the
site's own server; the page contains no address of the provider (YouTube,
Vimeo, Digistore24 …), not in a link, not in an attribute, not in a script.
No connection to the provider is made, no cookie is set, nothing is stored in
the browser.

## The preview image

The site's server fetches the preview image from the provider once (YouTube:
`img.youtube.com`; Vimeo: the image address named in Vimeo's public oEmbed
answer) and keeps a copy in `storage/shoji/thumbs/` for 30 days. The request
carries the video id and a fixed user agent, never data about a visitor.
Images are only fetched for addresses the site itself rendered (signed).

## After the click

The browser loads the provider's content: for a video the player from
`www.youtube-nocookie.com` or `player.vimeo.com` (with `dnt=1`), for
Digistore24 the order form from `www.digistore24.com` and the script from
`www.digistore24-scripts.com`. The browser first asks the site
(`/shoji/frame/…`) and is redirected; from then on the provider receives
what a browser sends with every request — IP address, browser details, the
address of the embedding page as referrer — and its own privacy policy
applies. The provider may set cookies or use browser storage within its
frame.

Shoji does not remember the click: no cookie, no storage. On the next page,
and on the next visit, the visitor is asked again.

## Without JavaScript

The button is a plain link. It leads (through the site's `/shoji/go/…`
address) to the content on the provider's website, like any other external
link.

## Stored on the server

- `storage/shoji/secret`: a random value that signs the site's own
  addresses. No personal data.
- `storage/shoji/thumbs/<provider>/<id>.jpg|png|webp`: copies of preview
  images, replaced after 30 days; `.miss` files for images that could not be
  fetched. No personal data.

No log of clicks or visitors is written.
