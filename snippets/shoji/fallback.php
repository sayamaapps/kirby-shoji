<?php
/**
 * An address of a protected provider (YouTube, Vimeo, Digistore24) that
 * Shoji cannot embed: a note and a plain link to the address, nothing else.
 * No iframe, no <video>, no script, no request to the provider before the
 * visitor follows the link. The (video:) tag and the video block print it
 * where Kirby's own output would load the provider with the page.
 *
 * The container has no data-shoji: shoji.js leaves it alone. Link and note
 * wear the classes of the placeholder's button and note, so a site's CSS for
 * the placeholder styles this one, too. A site with its own
 * site/snippets/shoji/fallback.php overrides this one.
 *
 * @var string|null $url the pasted address
 */

use Shoji\I18n;
use Shoji\Providers;

$provider = isset($url) && is_string($url) ? Providers::protectedProvider($url) : null;
if ($provider === null) {
	return;
}
shoji_i18n();
$names = ['name' => Providers::PROTECTED[$provider]['name']];
$href = Providers::plainLink($url);
?>
<div class="<?= esc('shoji shoji-fallback shoji-' . $provider) ?>">
  <?php if ($href !== null) : ?>
  <a class="shoji-button" href="<?= esc($href) ?>" rel="noopener"><?= esc(I18n::t('open', $names)) ?></a>
  <?php endif ?>
  <p class="shoji-note"><?= esc(I18n::t('fallback', $names)) ?></p>
</div>
