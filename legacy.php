<?php

/**
 * Names of the 1.x API, kept until the next major version (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases and
 * function wrappers only; no logic here.
 */

class_alias(\Sayamaapps\Shoji\Embed::class, 'Shoji\\Embed');
class_alias(\Sayamaapps\Shoji\Http::class, 'Shoji\\Http');
class_alias(\Sayamaapps\Shoji\I18n::class, 'Shoji\\I18n');
class_alias(\Sayamaapps\Shoji\Options::class, 'Shoji\\Options');
class_alias(\Sayamaapps\Shoji\Providers::class, 'Shoji\\Providers');
class_alias(\Sayamaapps\Shoji\Signer::class, 'Shoji\\Signer');
class_alias(\Sayamaapps\Shoji\Thumbs::class, 'Shoji\\Thumbs');

if (Kirby\Cms\Helpers::hasOverride('shoji_option') === false) {
	/** Reads `shoji.<key>`. Deprecated: use Sayamaapps\Shoji\Api::option(). */
	function shoji_option(string $key, mixed $default = null): mixed
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_option() is deprecated, use Sayamaapps\\Shoji\\Api::option()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::option(Kirby\Cms\App::instance(), $key, $default);
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_embed') === false) {
	/** Embed for an address or a provider and id. Deprecated: use Sayamaapps\Shoji\Api::embed(). */
	function shoji_embed(string $urlOrProvider, string|null $id = null, array $params = [], string|null $title = null): Sayamaapps\Shoji\Embed|null
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_embed() is deprecated, use Sayamaapps\\Shoji\\Api::embed()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::embed(Kirby\Cms\App::instance(), $urlOrProvider, $id, $params, $title);
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_i18n') === false) {
	/** Language and texts for this request. Deprecated: use Sayamaapps\Shoji\Api::i18n(). */
	function shoji_i18n(): void
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_i18n() is deprecated, use Sayamaapps\\Shoji\\Api::i18n()',
			'shoji-function'
		);

		Sayamaapps\Shoji\Api::i18n(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_script_url') === false) {
	/** Address of assets/shoji.js. Deprecated: use Sayamaapps\Shoji\Api::scriptUrl(). */
	function shoji_script_url(): string|null
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_script_url() is deprecated, use Sayamaapps\\Shoji\\Api::scriptUrl()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::scriptUrl(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_script_tag') === false) {
	/** The script tag for shoji.js, once per page. Deprecated: use Sayamaapps\Shoji\Api::scriptTag(). */
	function shoji_script_tag(): string
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_script_tag() is deprecated, use Sayamaapps\\Shoji\\Api::scriptTag()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::scriptTag(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_route') === false) {
	/** The response of a /shoji/… address. Deprecated: use Sayamaapps\Shoji\Api::route(). */
	function shoji_route(string $kind, string $provider, string $id): Kirby\Http\Response|false
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_route() is deprecated, use Sayamaapps\\Shoji\\Api::route()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::route(Kirby\Cms\App::instance(), $kind, $provider, $id);
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_video') === false) {
	/** What the (video:) tag and the video block print for an address. Deprecated: use Sayamaapps\Shoji\Api::video(). */
	function shoji_video(Kirby\Cms\App $kirby, string $url): string|null
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_video() is deprecated, use Sayamaapps\\Shoji\\Api::video()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::video($kirby, $url);
	}
}

if (Kirby\Cms\Helpers::hasOverride('shoji_core_tag') === false) {
	/** Kirby's own tag definition for a tag name. Deprecated: use Sayamaapps\Shoji\Api::coreTag(). */
	function shoji_core_tag(string $name): array|null
	{
		Kirby\Cms\Helpers::deprecated(
			'shoji_core_tag() is deprecated, use Sayamaapps\\Shoji\\Api::coreTag()',
			'shoji-function'
		);

		return Sayamaapps\Shoji\Api::coreTag(Kirby\Cms\App::instance(), $name);
	}
}
