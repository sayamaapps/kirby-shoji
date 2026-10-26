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
function idIn(array $definition, string $url): ?string
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
