<?php
/**
 * The placeholder, the plain default shipped with Shoji. No CSS: the look
 * belongs to the site (classes shoji-*). For your own markup create
 * site/snippets/shoji/embed.php (Kirby takes that one first) and build it
 * from Api::embed() — see the README.
 *
 *   <?php snippet('shoji/embed', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Trailer']) ?>
 *   <?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'params' => ['form' => '78901']]) ?>
 *
 * Attribute values are always double-quoted, so esc() (HTML context:
 * & < > " ' encoded) is enough and keeps addresses readable.
 *
 * Before the click the page names no provider host: the preview image, the
 * link, the iframe address, and for kind 'html' the address of the site's
 * own markup and the provider's scripts and stylesheets all point to
 * /shoji/… on this site. Without JavaScript the button is a plain link that
 * leads to the provider (kind 'html' without a `link`: to the bare markup).
 *
 * @var \Sayamaapps\Shoji\Embed|null $embed   ready-made, or:
 * @var string|null $url     a pasted address, or:
 * @var string|null $provider  with $id
 * @var string|null $id
 * @var array|null $params  extra parameters for the provider address (signed)
 * @var string|null $title   video title: alt text, button, iframe title
 * @var string|null $label   button text instead of the default
 * @var string|false|null $note    note text instead of the default; false = none
 * @var string|null $class   extra classes on the container
 * @var string|null $buttonClass extra classes on the button
 * @var string|null $frameClass extra classes on the iframe
 * @var array|null $attrs   extra container attributes (data-*, aria-*, id only)
 * @var bool|null $open    true: the content right away, without placeholder
 *                (for a site that remembers consent itself);
 *                false: placeholder; not given (or no bool):
 *                the provider's switch in `shoji.open`
 */

use Sayamaapps\Shoji\Api;
use Sayamaapps\Shoji\I18n;
use Sayamaapps\Shoji\Options;

$embed ??= isset($url) && is_string($url)
	? Api::embed(kirby(), $url, null, $params ?? [], $title ?? null)
	: (isset($provider, $id) ? Api::embed(kirby(), (string)$provider, (string)$id, $params ?? [], $title ?? null) : null);
if ($embed === null) {
	return;
}
Api::i18n(kirby());
$t = fn (string $key, array $values = []) => I18n::t($key, $values);
$names = ['name' => $embed->name(), 'company' => $embed->company()];

// Switched off: a plain link to the provider. Loads nothing, breaks nothing.
if (!Options::active(kirby())) {
	if ($target = $embed->target('go')) : ?>
<a class="shoji-link" href="<?= esc($target) ?>" rel="noopener"><?= esc($embed->title ?? $t('open', $names)) ?></a>
    <?php endif;
	return;
}

$classes = trim('shoji shoji-' . $embed->kind() . ' shoji-' . $embed->provider . ' ' . ($class ?? ''));
$frameTitle = $embed->title ?? $t('frame', $names);
$frameClassAttr = esc(trim('shoji-frame ' . ($frameClass ?? '')));
$frameSrc = esc($embed->frameUrl());
$frameAllow = esc($embed->allow());
// The call decides if it says true or false; otherwise the provider's switch.
$open = is_bool($open ?? null) ? $open : $embed->open();
$extra = [];
foreach (is_array($attrs ?? null) ? $attrs : [] as $name => $value) {
	if (is_string($name) && preg_match('/^(?:(?:data|aria)-[a-z0-9-]+|id)$/', $name) && is_scalar($value)) {
		$extra[$name] = (string)$value;
	}
}

if ($open && $embed->kind() === 'html') : ?>
<div class="<?= esc($classes . ' shoji-opened') ?>" data-shoji-provider="<?= esc($embed->provider) ?>"<?= $extra ? ' ' . attr($extra) : '' ?>>
  <?php foreach ($embed->styleUrls() as $style) : ?><link rel="stylesheet" href="<?= esc($style) ?>"><?php endforeach ?>
  <div class="shoji-markup"><?= $embed->html() ?></div>
  <?php foreach ($embed->scriptUrls() as $script) : ?><script src="<?= esc($script) ?>" defer></script><?php endforeach ?>
</div>
<?php
	return; elseif ($open) : ?>
<div class="<?= esc($classes . ' shoji-opened') ?>" data-shoji-provider="<?= esc($embed->provider) ?>"<?= $extra ? ' ' . attr($extra) : '' ?>>
  <?php foreach ($embed->scriptUrls() as $script) : ?><script src="<?= esc($script) ?>" defer></script><?php endforeach ?>
  <iframe class="<?= $frameClassAttr ?>" src="<?= $frameSrc ?>" title="<?= esc($frameTitle) ?>" allow="<?= $frameAllow ?>" allowfullscreen loading="lazy"></iframe>
</div>
<?php
	return;
endif;

$label ??= match ($embed->kind()) {
	'video' => $embed->title !== null ? $t('button.videoTitle', ['title' => $embed->title]) : $t('button.video'),
	'form', 'html' => $t('button.form'),
	default => $t('button.content'),
};
$note ??= $t('note.' . $embed->kind(), $names);
$privacy = $note !== false ? Options::privacyUrl(kirby()) : null;
$thumb = $embed->thumbUrl();
$size = $embed->size();
// Kind 'html' loads the site's markup instead of an iframe. Several scripts
// or stylesheets: own addresses separated by a space, loaded in this order.
$data = ['data-shoji-provider' => $embed->provider];
if ($html = $embed->htmlUrl()) {
	$data['data-shoji-html'] = $html;
} else {
	$data += ['data-shoji-frame' => $embed->frameUrl(), 'data-shoji-title' => $frameTitle, 'data-shoji-allow' => $embed->allow()];
}
$data['data-shoji-script'] = implode(' ', $embed->scriptUrls());
$data['data-shoji-style'] = implode(' ', $embed->styleUrls());
$data['data-shoji-frame-class'] = $html ? '' : ($frameClass ?? '');
$dataAttrs = '';
foreach ($data as $name => $value) {
	if ($value !== '') {
		$dataAttrs .= ' ' . $name . '="' . esc($value) . '"';
	}
}
$thumbAlt = esc($embed->title !== null ? $t('thumbAlt', ['title' => $embed->title]) : $t('thumbAltUntitled', $names));
$sizeAttrs = $size ? ' width="' . $size[0] . '" height="' . $size[1] . '"' : '';
?>
<div class="<?= esc($classes) ?>" data-shoji<?= $dataAttrs ?><?= $extra ? ' ' . attr($extra) : '' ?>>
  <?php if ($thumb) : ?>
  <img class="shoji-thumb" src="<?= esc($thumb) ?>" alt="<?= $thumbAlt ?>"<?= $sizeAttrs ?> loading="lazy" decoding="async">
  <?php endif ?>
  <a class="<?= esc(trim('shoji-button ' . ($buttonClass ?? ''))) ?>" href="<?= esc($embed->linkUrl()) ?>" rel="nofollow"><?= esc($label) ?></a>
  <?php if ($note !== false && $note !== '') : ?>
  <p class="shoji-note"><?= esc($note) ?><?php if ($privacy) : ?> <a class="shoji-privacy" href="<?= esc($privacy) ?>"><?= esc($t('privacy')) ?></a><?php endif ?></p>
  <?php endif ?>
</div>
<?= Api::scriptTag(kirby()) ?>
