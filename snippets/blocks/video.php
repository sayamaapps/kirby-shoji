<?php
/**
 * Video block (replaces Kirby's own). A YouTube or Vimeo address pasted in
 * the Panel becomes a Shoji placeholder, one Shoji cannot embed a note with a
 * plain link (Api::video(), the same as for the (video:) tag); a video from
 * the media library, another host, or Shoji switched off: Kirby's own block,
 * unchanged.
 *
 * A site with its own site/snippets/blocks/video.php overrides this one.
 *
 * @var \Kirby\Cms\Block $block
 */

use Sayamaapps\Shoji\Api;

$kirby = $block->kirby();
$content = $block->location()->value() !== 'kirby' ? Api::video($kirby, $block->url()->value()) : null;

if ($content === null) {
	include $kirby->root('kirby') . '/config/blocks/video/video.php';
	return;
}
$caption = $block->caption();
?>
<figure>
  <?= $content ?>
  <?php if ($caption->isNotEmpty()) : ?>
  <figcaption><?= $caption ?></figcaption>
  <?php endif ?>
</figure>
