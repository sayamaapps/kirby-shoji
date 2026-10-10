<?php
/**
 * The placeholder, the plain default shipped with Shoji. No CSS: the look
 * belongs to the site (classes shoji-*). For your own markup create
 * site/snippets/shoji/embed.php (Kirby takes that one first) and build it
 * from shoji_embed() — see the README.
 *
 *   <?php snippet('shoji/embed', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Trailer']) ?>
 *   <?php snippet('shoji/embed', ['provider' => 'digistore', 'id' => '123456', 'params' => ['form' => '78901']]) ?>
 *
 * Attribute values are always double-quoted, so esc() (HTML context:
 * & < > " ' encoded) is enough and keeps addresses readable.
 *
 * Before the click the page names no provider host: the preview image, the
 * link and the iframe address all point to /shoji/… on this site. Without
 * JavaScript the button is a plain link that leads to the provider.
 *
 * @var \Shoji\Embed|null $embed   ready-made, or:
 * @var string|null       $url     a pasted address, or:
 * @var string|null       $provider  with $id
 * @var string|null       $id
 * @var array|null        $params  extra parameters for the provider address (signed)
 * @var string|null       $title   video title: alt text, button, iframe title
 * @var string|null       $label   button text instead of the default
 * @var string|false|null $note    note text instead of the default; false = none
 * @var string|null       $class   extra classes on the container
 * @var string|null       $buttonClass extra classes on the button
 * @var string|null       $frameClass extra classes on the iframe
 * @var array|null        $attrs   extra container attributes (data-*, aria-*, id only)
 * @var bool|null         $open    true: the content right away, without placeholder
 *                                 (for a site that remembers consent itself)
 */

use Shoji\I18n;
use Shoji\Options;

$embed ??= isset($url) && is_string($url)
    ? shoji_embed($url, null, $params ?? [], $title ?? null)
    : (isset($provider, $id) ? shoji_embed((string) $provider, (string) $id, $params ?? [], $title ?? null) : null);
if ($embed === null) {
    return;
}
shoji_i18n();
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
$extra = [];
foreach (is_array($attrs ?? null) ? $attrs : [] as $name => $value) {
    if (is_string($name) && preg_match('/^(?:(?:data|aria)-[a-z0-9-]+|id)$/', $name) && is_scalar($value)) {
        $extra[$name] = (string) $value;
    }
}

if (($open ?? false) === true) : ?>
<div class="<?= esc($classes . ' shoji-opened') ?>" data-shoji-provider="<?= esc($embed->provider) ?>"<?= $extra ? ' ' . attr($extra) : '' ?>>
  <?php if ($script = $embed->scriptUrl()) : ?><script src="<?= esc($script) ?>" defer></script><?php endif ?>
  <iframe class="<?= esc(trim('shoji-frame ' . ($frameClass ?? ''))) ?>" src="<?= esc($embed->frameUrl()) ?>" title="<?= esc($frameTitle) ?>" allow="<?= esc($embed->allow()) ?>" allowfullscreen loading="lazy"></iframe>
</div>
<?php
    return;
endif;

$label ??= match ($embed->kind()) {
    'video' => $embed->title !== null ? $t('button.videoTitle', ['title' => $embed->title]) : $t('button.video'),
    'form'  => $t('button.form'),
    default => $t('button.content'),
};
$note ??= $t('note.' . $embed->kind(), $names);
$privacy = $note !== false ? Options::privacyUrl(kirby()) : null;
$thumb = $embed->thumbUrl();
$size = $embed->size();
?>
<div class="<?= esc($classes) ?>" data-shoji data-shoji-provider="<?= esc($embed->provider) ?>" data-shoji-frame="<?= esc($embed->frameUrl()) ?>" data-shoji-title="<?= esc($frameTitle) ?>" data-shoji-allow="<?= esc($embed->allow()) ?>"<?php if ($script = $embed->scriptUrl()) : ?> data-shoji-script="<?= esc($script) ?>"<?php endif ?><?php if (!empty($frameClass)) : ?> data-shoji-frame-class="<?= esc($frameClass) ?>"<?php endif ?><?= $extra ? ' ' . attr($extra) : '' ?>>
  <?php if ($thumb) : ?>
  <img class="shoji-thumb" src="<?= esc($thumb) ?>" alt="<?= esc($embed->title !== null ? $t('thumbAlt', ['title' => $embed->title]) : $t('thumbAltUntitled', $names)) ?>"<?php if ($size) : ?> width="<?= $size[0] ?>" height="<?= $size[1] ?>"<?php endif ?> loading="lazy" decoding="async">
  <?php endif ?>
  <a class="<?= esc(trim('shoji-button ' . ($buttonClass ?? ''))) ?>" href="<?= esc($embed->linkUrl()) ?>" rel="nofollow"><?= esc($label) ?></a>
  <?php if ($note !== false && $note !== '') : ?>
  <p class="shoji-note"><?= esc($note) ?><?php if ($privacy) : ?> <a class="shoji-privacy" href="<?= esc($privacy) ?>"><?= esc($t('privacy')) ?></a><?php endif ?></p>
  <?php endif ?>
</div>
<?= shoji_script_tag() ?>
