<?php

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Template\Snippet;
use PHPUnit\Framework\TestCase;

/**
 * Base of the test classes that boot Kirby. Every site directory a test makes
 * is removed and the Kirby app destroyed in tearDown, also when a test fails.
 */
abstract class ShojiTestCase extends TestCase
{
	/** @var list<string> */
	private array $directories = [];

	/**
	 * A fresh Kirby instance on a throwaway site directory with these options.
	 *
	 * @return array{0: App, 1: string} the app and its site directory
	 */
	protected function site(array $options = [], array $query = []): array
	{
		$tmp = $this->track(sys_get_temp_dir() . '/shoji-test-' . bin2hex(random_bytes(6)));
		foreach (['public', 'content', 'site/plugins', 'site/templates', 'storage'] as $dir) {
			mkdir($tmp . '/' . $dir, 0700, true);
		}
		file_put_contents($tmp . '/site/templates/default.php', 'Fixture');
		Snippet::$cache = []; // Kirby remembers snippet paths per process
		$app = new App([
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

	protected function tearDown(): void
	{
		App::destroy();
		foreach ($this->directories as $directory) {
			Dir::remove($directory);
		}
		$this->directories = [];
		parent::tearDown();
	}

	/** Registers a site directory a test creates itself; it is removed in tearDown. */
	protected function track(string $directory): string
	{
		$this->directories[] = $directory;
		return $directory;
	}
}
