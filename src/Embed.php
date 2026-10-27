<?php

namespace Sayamaapps\Shoji;

use Closure;
use Kirby\Cms\App;
use WeakMap;

/**
 * One piece of third-party content behind a placeholder: provider, id,
 * parameters, title. Knows Shoji's own addresses for it (all signed, all on
 * this site) and, for the routes, the provider's addresses.
 *
 * The default snippet uses it; a site with its own markup gets one from
 * Api::embed() and builds the placeholder from these methods.
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Embed
{
	/**
	 * @param array<string,string> $params already cleaned (Providers::params)
	 */
	private function __construct(
		private readonly App $kirby,
		public readonly string $provider,
		public readonly array $definition,
		public readonly string $id,
		public readonly array $params,
		public readonly string|null $title,
	) {
	}

	/**
	 * Value of the iframe `allow` attribute for this provider.
	 */
	public function allow(): string
	{
		return Providers::allow($this->definition);
	}

	/** @return list<string> the provider's scripts or stylesheets, in order */
	private function assets(string $key): array
	{
		return $key === 'style' && $this->kind() !== 'html' ? [] : Providers::assets($this->definition, $key) ?? [];
	}

	/**
	 * The company that learns the visitor's IP address, as the note names it.
	 */
	public function company(): string
	{
		return $this->definition['company'];
	}

	/** @return array<string,string> */
	public static function decodeParams(string $encoded): array
	{
		if ($encoded === '') {
			return [];
		}
		parse_str($encoded, $params);
		return Providers::params($params);
	}

	/** What the signature covers for the second and later script or stylesheet. */
	public static function encodeIndex(int $index): string
	{
		return 'n=' . $index;
	}

	// Parameters in the address and the signature.

	/**
	 * The parameters as the query string of an address, or '' without parameters.
	 */
	public static function encodeParams(array $params): string
	{
		return $params === [] ? '' : http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Shoji's own address of the iframe (/shoji/frame/…, signed).
	 */
	public function frameUrl(): string
	{
		return $this->ownUrl('frame', true);
	}

	/**
	 * From a pasted address (YouTube, Vimeo, Digistore24 …); null if no
	 * provider recognises it. The address gives the id and what the provider
	 * says it carries (`urlParams`: a Vimeo hash, a Digistore24 order form,
	 * affiliate and campaign); $params given explicitly win over that. An
	 * address in a form the provider cannot carry is not taken over.
	 */
	public static function fromUrl(App $kirby, string $url, array $params = [], string|null $title = null): self|null
	{
		foreach (self::providers($kirby) as $name => $definition) {
			if (!isset($definition['match']) || !preg_match($definition['match'], $url, $m) || !isset($m[1])) {
				continue;
			}
			$carried = [];
			$urlParams = $definition['urlParams'] ?? null;
			if ($urlParams instanceof Closure) {
				$carried = $urlParams($url, $m);
				if (!is_array($carried)) {
					continue;
				}
			}
			$embed = self::make($kirby, $name, $m[1], Providers::params($params) + Providers::params($carried), $title);
			if ($embed !== null) {
				return $embed;
			}
		}
		return null;
	}

	/**
	 * True if the provider has at least one script.
	 */
	public function hasScript(): bool
	{
		return $this->assets('script') !== [];
	}

	/**
	 * True if the provider names preview image candidates.
	 */
	public function hasThumbnail(): bool
	{
		$thumbnails = $this->definition['thumbnails'] ?? [];
		return $thumbnails instanceof Closure || $thumbnails !== [];
	}

	/**
	 * Kind 'html': the markup from the site's snippet, rendered for the
	 * /shoji/html/… route (after the click) or the open mode. The snippet
	 * gets $embed and $params (the signed parameters); it runs in that
	 * request, not in the page's, so values from the page travel as params.
	 * Null if the snippet is missing or prints nothing.
	 */
	public function html(): string|null
	{
		$snippet = $this->snippet();
		if ($snippet === null) {
			return null;
		}
		$html = trim((string)$this->kirby->snippet($snippet, ['embed' => $this, 'params' => $this->params], true));
		return $html !== '' ? $html : null;
	}

	/** Kind 'html': the own address that serves the markup; null for other kinds. */
	public function htmlUrl(): string|null
	{
		return $this->kind() === 'html' ? $this->ownUrl('html', true) : null;
	}

	/**
	 * The provider's kind: 'video', 'form', 'content' or 'html'; 'content' for anything else.
	 */
	public function kind(): string
	{
		$kind = $this->definition['kind'] ?? 'content';
		return in_array($kind, Providers::KINDS, true) ? $kind : 'content';
	}

	/**
	 * The plain link: works without JavaScript, redirects to the provider on
	 * click. Kind 'html' without a `link`: the markup itself (a bare form
	 * still submits to the provider).
	 */
	public function linkUrl(): string
	{
		return $this->kind() === 'html' && !isset($this->definition['link'])
			? $this->ownUrl('html', true)
			: $this->ownUrl('go', true);
	}

	/**
	 * From provider name and id; null if the provider is unknown or the id
	 * invalid. Works while Shoji is switched off, too (the snippet then
	 * prints a plain link).
	 */
	public static function make(App $kirby, string $provider, string $id, array $params = [], string|null $title = null): self|null
	{
		$definition = self::providers($kirby)[$provider] ?? null;
		$id = trim($id);
		if ($definition === null || !Providers::validId($definition, $id)) {
			return null;
		}
		$title = $title !== null && trim($title) !== '' ? trim($title) : null;
		return new self($kirby, $provider, $definition, $id, Providers::params($params), $title);
	}

	/**
	 * The provider's name, as the note and the button show it.
	 */
	public function name(): string
	{
		return $this->definition['name'];
	}

	/**
	 * The provider's switch in `sayamaapps.shoji.open`: true if its content loads with
	 * the page instead of after the click. A single call's `open` (snippet
	 * data) wins over it in both directions.
	 */
	public function open(): bool
	{
		return Options::open($this->kirby)[$this->provider] ?? false;
	}

	/**
	 * /shoji/<kind>/<provider>/<id>?p=<params>&s=<signature>. Parameters are
	 * part of the signature, so nobody can change them in a copied address.
	 * The second and later script or stylesheet carry their index (n=1 …),
	 * signed as well; the first has none, so its address is the same as in 1.0.
	 */
	private function ownUrl(string $kind, bool $withParams, int $index = 0): string
	{
		$p = $withParams ? self::encodeParams($this->params) : '';
		$signed = $index > 0 ? self::encodeIndex($index) : $p;
		$query = ['s' => self::signer($this->kirby)->sign($kind, $this->provider, $this->id, $signed)];
		if ($p !== '') {
			$query = ['p' => $p] + $query;
		}
		if ($index > 0) {
			$query = ['n' => (string)$index] + $query;
		}
		return $this->kirby->url() . '/shoji/' . $kind . '/' . $this->provider . '/' . $this->id
			. '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/** @return array<string, array> */
	public static function providers(App $kirby): array
	{
		// Per Kirby instance (tests build several in one process)
		static $cache = null;
		$cache ??= new WeakMap();
		return $cache[$kirby] ??= Providers::all(Options::get($kirby, 'providers'));
	}

	/** The first provider script's own address, or null. */
	public function scriptUrl(): string|null
	{
		return $this->scriptUrls()[0] ?? null;
	}

	/** @return list<string> own addresses of every provider script, in order */
	public function scriptUrls(): array
	{
		return array_map(fn (int $n) => $this->ownUrl('script', false, $n), array_keys($this->assets('script')));
	}

	/**
	 * The signer of Shoji's own addresses for this Kirby instance.
	 */
	public static function signer(App $kirby): Signer
	{
		static $cache = null;
		$cache ??= new WeakMap();
		return $cache[$kirby] ??= new Signer(Options::secret($kirby));
	}

	/** [width, height] of the preview image, or null. */
	public function size(): array|null
	{
		$size = $this->definition['size'] ?? null;
		return is_array($size) && count($size) === 2 && (int)$size[0] > 0 && (int)$size[1] > 0
			? [(int)$size[0], (int)$size[1]]
			: null;
	}

	/**
	 * Kind 'html': name of the site's snippet with the markup ({id} filled
	 * in), null for every other kind.
	 */
	public function snippet(): string|null
	{
		return $this->kind() === 'html' ? str_replace('{id}', $this->id, (string)$this->definition['snippet']) : null;
	}

	/** @return list<string> kind 'html': own addresses of the provider's stylesheets */
	public function styleUrls(): array
	{
		return array_map(fn (int $n) => $this->ownUrl('style', false, $n), array_keys($this->assets('style')));
	}

	// The provider's addresses — only the routes use these.

	/**
	 * Where /shoji/<kind>/… redirects to: the provider address from the
	 * definition, passed through the `sayamaapps.shoji.url` hook. Null if the provider
	 * has none for this kind or the hook returned no https address.
	 */
	public function target(string $kind, int $index = 0): string|null
	{
		$template = match ($kind) {
			'frame'  => $this->definition['embed'] ?? null,
			'go'     => $this->definition['link'] ?? $this->definition['embed'] ?? null,
			'script', 'style' => $this->assets($kind)[$index] ?? null,
			default  => null,
		};
		if ($template === null) {
			return null;
		}
		$url = Providers::fill($template, $this->id, $kind === 'script' || $kind === 'style' ? [] : $this->params);
		$hook = Options::get($this->kirby, 'url');
		if ($hook instanceof Closure) {
			$url = $hook($url, $this, $kind);
		}
		return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
	}

	/**
	 * Candidate addresses of the preview image, in order of preference.
	 *
	 * @param $fetch fn (string $url): array{0:int,1:string}
	 * @return list<string>
	 */
	public function thumbnailCandidates(Closure $fetch): array
	{
		$thumbnails = $this->definition['thumbnails'] ?? [];
		if ($thumbnails instanceof Closure) {
			$thumbnails = $thumbnails($this->id, $fetch, $this->params);
		}
		$urls = [];
		foreach (is_array($thumbnails) ? $thumbnails : [] as $template) {
			if (is_string($template)) {
				$url = Providers::fill($template, $this->id, []);
				if (str_starts_with($url, 'https://')) {
					$urls[] = $url;
				}
			}
		}
		return $urls;
	}

	// Shoji's own addresses — the only ones that appear in the page.

	/**
	 * Shoji's own address of the preview image, or null without one.
	 */
	public function thumbUrl(): string|null
	{
		return $this->hasThumbnail() ? $this->ownUrl('thumb', true) : null;
	}
}
