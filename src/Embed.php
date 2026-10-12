<?php

namespace Shoji;

use Closure;
use Kirby\Cms\App;

/**
 * One piece of third-party content behind a placeholder: provider, id,
 * parameters, title. Knows Shoji's own addresses for it (all signed, all on
 * this site) and, for the routes, the provider's addresses.
 *
 * The default snippet uses it; a site with its own markup gets one from
 * shoji_embed() and builds the placeholder from these methods.
 */
final class Embed
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
        public readonly ?string $title,
    ) {
    }

    /**
     * From provider name and id; null if the provider is unknown or the id
     * invalid. Works while Shoji is switched off, too (the snippet then
     * prints a plain link).
     */
    public static function make(App $kirby, string $provider, string $id, array $params = [], ?string $title = null): ?self
    {
        $definition = self::providers($kirby)[$provider] ?? null;
        $id = trim($id);
        if ($definition === null || !Providers::validId($definition, $id)) {
            return null;
        }
        $title = $title !== null && trim($title) !== '' ? trim($title) : null;
        return new self($kirby, $provider, $definition, $id, Providers::params($params), $title);
    }

    /** From a pasted address (YouTube, Vimeo, Digistore24 …); null if no provider recognises it. */
    public static function fromUrl(App $kirby, string $url, array $params = [], ?string $title = null): ?self
    {
        foreach (self::providers($kirby) as $name => $definition) {
            if (isset($definition['match']) && preg_match($definition['match'], $url, $m) && isset($m[1])) {
                return self::make($kirby, $name, $m[1], $params, $title);
            }
        }
        return null;
    }

    /** @return array<string, array> */
    public static function providers(App $kirby): array
    {
        // Per Kirby instance (tests build several in one process)
        static $cache = null;
        $cache ??= new \WeakMap();
        return $cache[$kirby] ??= Providers::all(Options::get($kirby, 'providers'));
    }

    public function name(): string
    {
        return $this->definition['name'];
    }

    public function company(): string
    {
        return $this->definition['company'];
    }

    public function kind(): string
    {
        $kind = $this->definition['kind'] ?? 'content';
        return in_array($kind, Providers::KINDS, true) ? $kind : 'content';
    }

    public function hasThumbnail(): bool
    {
        $thumbnails = $this->definition['thumbnails'] ?? [];
        return $thumbnails instanceof Closure || $thumbnails !== [];
    }

    public function hasScript(): bool
    {
        return $this->assets('script') !== [];
    }

    /**
     * Kind 'html': name of the site's snippet with the markup ({id} filled
     * in), null for every other kind.
     */
    public function snippet(): ?string
    {
        return $this->kind() === 'html' ? str_replace('{id}', $this->id, $this->definition['snippet']) : null;
    }

    /**
     * Kind 'html': the markup from the site's snippet, rendered for the
     * /shoji/html/… route (after the click) or the open mode. The snippet
     * gets $embed and $params (the signed parameters); it runs in that
     * request, not in the page's, so values from the page travel as params.
     * Null if the snippet is missing or prints nothing.
     */
    public function html(): ?string
    {
        $snippet = $this->snippet();
        if ($snippet === null) {
            return null;
        }
        $html = trim((string) $this->kirby->snippet($snippet, ['embed' => $this, 'params' => $this->params], true));
        return $html !== '' ? $html : null;
    }

    /** @return list<string> the provider's scripts or stylesheets, in order */
    private function assets(string $key): array
    {
        return $key === 'style' && $this->kind() !== 'html' ? [] : Providers::assets($this->definition, $key) ?? [];
    }

    /** [width, height] of the preview image, or null. */
    public function size(): ?array
    {
        $size = $this->definition['size'] ?? null;
        return is_array($size) && count($size) === 2 && (int) $size[0] > 0 && (int) $size[1] > 0
            ? [(int) $size[0], (int) $size[1]]
            : null;
    }

    public function allow(): string
    {
        return Providers::allow($this->definition);
    }

    // Shoji's own addresses — the only ones that appear in the page.

    public function thumbUrl(): ?string
    {
        return $this->hasThumbnail() ? $this->ownUrl('thumb', false) : null;
    }

    public function frameUrl(): string
    {
        return $this->ownUrl('frame', true);
    }

    /** Kind 'html': the own address that serves the markup; null for other kinds. */
    public function htmlUrl(): ?string
    {
        return $this->kind() === 'html' ? $this->ownUrl('html', true) : null;
    }

    /** The first provider script's own address, or null. */
    public function scriptUrl(): ?string
    {
        return $this->scriptUrls()[0] ?? null;
    }

    /** @return list<string> own addresses of every provider script, in order */
    public function scriptUrls(): array
    {
        return array_map(fn (int $n) => $this->ownUrl('script', false, $n), array_keys($this->assets('script')));
    }

    /** @return list<string> kind 'html': own addresses of the provider's stylesheets */
    public function styleUrls(): array
    {
        return array_map(fn (int $n) => $this->ownUrl('style', false, $n), array_keys($this->assets('style')));
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
            $query = ['n' => (string) $index] + $query;
        }
        return $this->kirby->url() . '/shoji/' . $kind . '/' . $this->provider . '/' . $this->id
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    // The provider's addresses — only the routes use these.

    /**
     * Where /shoji/<kind>/… redirects to: the provider address from the
     * definition, passed through the `shoji.url` hook. Null if the provider
     * has none for this kind or the hook returned no https address.
     */
    public function target(string $kind, int $index = 0): ?string
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
     * @param Closure $fetch fn (string $url): array{0:int,1:string}
     * @return list<string>
     */
    public function thumbnailCandidates(Closure $fetch): array
    {
        $thumbnails = $this->definition['thumbnails'] ?? [];
        if ($thumbnails instanceof Closure) {
            $thumbnails = $thumbnails($this->id, $fetch);
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

    // Parameters in the address and the signature.

    public static function encodeParams(array $params): string
    {
        return $params === [] ? '' : http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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

    public static function signer(App $kirby): Signer
    {
        static $cache = null;
        $cache ??= new \WeakMap();
        return $cache[$kirby] ??= new Signer(Options::secret($kirby));
    }
}
