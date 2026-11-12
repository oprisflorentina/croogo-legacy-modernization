<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Puts a page rendered by Symfony inside Croogo's layout (transclusion).
 *
 * Croogo renders its layout around two placeholders at /legacy-layout,
 * built for the page being shown: its menu marks that page, and its blocks
 * follow their visibility rules for it. This service fetches it, keeps it for
 * a minute per page, and swaps the placeholders for Symfony's title and
 * content. The header, menu and sidebar stay Croogo's, so there's no second
 * copy of the layout to keep in sync.
 */
final class LegacyLayout
{
    private const TITLE = '%%LEGACY_LAYOUT_TITLE%%';
    private const CONTENT = '<!--%%LEGACY_LAYOUT_CONTENT%%-->';
    private const SECONDS_CACHED = 60;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire(env: 'CROOGO_INTERNAL_URL')]
        private readonly string $croogoUrl,
    ) {
    }

    /**
     * @param string $path    The page being shown, such as /contact
     * @param string $title   Plain text; it is escaped here
     * @param string $content HTML rendered by Symfony
     */
    public function wrap(string $path, string $title, string $content): string
    {
        $layout = $this->cache->get('legacy_layout.' . md5($path), function (ItemInterface $item) use ($path): string {
            $item->expiresAfter(self::SECONDS_CACHED);

            // getContent() throws if Croogo answers with an error, so a broken
            // layout is never cached or shown.
            $html = $this->httpClient
                ->request('GET', rtrim($this->croogoUrl, '/') . '/legacy-layout', ['query' => ['path' => $path]])
                ->getContent();
            if (!str_contains($html, self::TITLE) || !str_contains($html, self::CONTENT)) {
                throw new \RuntimeException('Croogo\'s /legacy-layout answered without its placeholders.');
            }

            return $html;
        });

        return str_replace(
            [self::TITLE, self::CONTENT],
            [htmlspecialchars($title, \ENT_QUOTES, 'UTF-8'), $content],
            $layout,
        );
    }
}
