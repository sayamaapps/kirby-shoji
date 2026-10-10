<?php
/**
 * Video block (replaces Kirby's own). A YouTube or Vimeo address pasted in
 * the Panel becomes a Shoji placeholder; a video from the media library,
 * anything else, or Shoji switched off: Kirby's own block, unchanged.
 *
 * A site with its own site/snippets/blocks/video.php overrides this one.
 *
 * @var \Kirby\Cms\Block $block
 */

use Shoji\Embed;
use Shoji\Options;

$kirby = $block->kirby();
$embed = $block->location()->value() !== 'kirby' && Options::active($kirby)
    ? Embed::fromUrl($kirby, $block->url()->value())
    : null;

if ($embed === null) {
    include $kirby->root('kirby') . '/config/blocks/video/video.php';
    return;
}
$caption = $block->caption();
?>
<figure>
  <?php snippet('shoji/embed', ['embed' => $embed]) ?>
  <?php if ($caption->isNotEmpty()) : ?>
  <figcaption><?= $caption ?></figcaption>
  <?php endif ?>
</figure>
