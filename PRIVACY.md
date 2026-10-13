# Shoji: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German text
block for privacy policies, which is the authoritative version. Both describe
the implementation. They are not legal advice. Each operator has to determine
the legal basis and the wording for their own site.

Everything below "Before the click" up to "Without JavaScript" is the default.
A provider the site switches on with `shoji.open` (or a single call with
`open => true`) skips the placeholder; see [Loaded right away](#loaded-right-away-shojiopen).
DATENSCHUTZ.md has each text block in both versions, "after the click" and
"right away"; `$site->shojiOpen('<provider>')` tells which one applies.

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

## HTML forms (kind `html`)

For a form whose markup the site keeps itself (a Brevo sign-up form, say),
the page holds only the placeholder. After the click the browser fetches the
form's markup from the site (`/shoji/html/…`, signed), then the provider's
stylesheets and scripts (Brevo: from `sibforms.com`, through the site's
`/shoji/style|script/…` addresses). From then on the provider receives what a
browser sends with every request. What the visitor types into the form
reaches the provider only when the form is submitted, together with hidden
fields the site added (a campaign key, for example). A site script that keeps
such a key in the browser's storage is the site's own, not Shoji's, and needs
its own mention in the privacy policy.

## Loaded right away (`shoji.open`)

Only for providers listed with `true` in `shoji.open` (or a single call with
`open => true`). Without the option nothing loads before the click. There is
no placeholder, no preview image and no note: the page does not tell the
visitor before the content loads, only the privacy policy does.

- **Videos and other iframe content** (YouTube, Vimeo …): the page holds an
  iframe pointing to the site (`/shoji/frame/…`, `loading="lazy"`). When it
  comes near the visible area (on the first screen: with the page) the
  browser asks the site, is redirected to the provider and loads the player,
  which loads further files from the provider's servers, whether or not the
  visitor plays the video.
- **Forms in an iframe** (Digistore24 …): the provider's script loads with the
  page wherever the form is (`/shoji/script/…`, redirected) and runs in the
  page itself, not only in the form's frame; the form's iframe loads like a
  video's.
- **HTML forms** (kind `html`, Brevo …): the form's markup is part of the page
  (its `action` names the provider's host), the provider's stylesheets and
  scripts load with the page (through `/shoji/style|script/…`, redirected) and
  the scripts run in the page. What the visitor types reaches the provider
  when the form is submitted.

In each case the provider receives what a browser sends with every request
(IP address, browser details, the site's address as referrer) without a
click, whether or not the visitor uses the content, and its own privacy
policy applies. Whether and on which legal basis a site loads a provider
without a click is the site's decision and has to be checked for that site;
Shoji makes no statement about it.

## Without JavaScript

The button is a plain link. It leads (through the site's `/shoji/go/…`
address) to the content on the provider's website, like any other external
link. For an HTML form without such a page it leads to the form's bare
markup on the site, which submits to the provider.

## Stored on the server

- `storage/shoji/secret`: a random value that signs the site's own
  addresses. No personal data.
- `storage/shoji/thumbs/<provider>/<id>.jpg|png|webp`: copies of preview
  images, replaced after 30 days; `.miss` files for images that could not be
  fetched. No personal data.

No log of clicks or visitors is written.
