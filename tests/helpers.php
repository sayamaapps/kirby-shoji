<?php
/**
 * Shared test helpers.
 *
 * Loaded by tests/bootstrap.php. The checks use PHPUnit's assertions, so a
 * failing check ends the test method and is reported with its label.
 */

use PHPUnit\Framework\Assert;

/** Strict comparison ($expected === $actual). */
function expect(mixed $expected, mixed $actual, string $what): void
{
	Assert::assertSame($expected, $actual, $what);
}

function holds(bool $ok, string $what): void
{
	Assert::assertTrue($ok, $what);
}

/** Id the provider's `match` finds in $url, or null. */
function idIn(array $definition, string $url): string|null
{
	return preg_match($definition['match'], $url, $m) ? $m[1] : null;
}

function removeTree(string $path): void
{
	if (!is_dir($path)) {
		return;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $file) {
		$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
	}
	rmdir($path);
}

/**
 * A fresh Kirby instance on a throwaway site directory with these options.
 * The caller removes the directory with removeTree() (its path is the
 * `roots` index of the returned app).
 *
 * @return array{0: \Kirby\Cms\App, 1: string}
 */
function shojiKirby(array $options = [], array $query = []): array
{
	$tmp = sys_get_temp_dir() . '/shoji-test-' . bin2hex(random_bytes(6));
	foreach (['public', 'content', 'site/plugins', 'site/templates', 'storage'] as $dir) {
		mkdir($tmp . '/' . $dir, 0700, true);
	}
	file_put_contents($tmp . '/site/templates/default.php', 'Fixture');
	Kirby\Template\Snippet::$cache = []; // Kirby remembers snippet paths per process
	$app = new Kirby\Cms\App([
		'roots' => [
			'index'   => $tmp . '/public',
			'content' => $tmp . '/content',
			'site'    => $tmp . '/site',
			'storage' => $tmp . '/storage',
		],
		'request' => ['query' => $query],
		'options' => ['url' => 'https://site.example'] + $options,
	]);
	return [$app, $tmp];
}
