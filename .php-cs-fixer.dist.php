<?php

// Kirby's style, with one exception: the class DocBlocks keep their @package
// line (`Kirby Shoji`). The Kirby set removes it; the plugin rules require it.
return Kirby\PhpCs\Config::create()
	->setRules(['@Kirby/style:risky' => true, 'phpdoc_no_package' => false])
	->setFinder(
		PhpCsFixer\Finder::create()
			->exclude('vendor')
			->in(__DIR__)
	);
