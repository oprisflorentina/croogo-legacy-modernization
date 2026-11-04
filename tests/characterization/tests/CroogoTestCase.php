<?php

declare(strict_types=1);

namespace Characterization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Base for all characterization tests: a real browser-like client that only
 * talks HTTP, so the same tests run against any server that serves the site.
 */
abstract class CroogoTestCase extends TestCase
{
    protected HttpBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = new HttpBrowser(HttpClient::create());
    }

    protected function url(string $path): string
    {
        return rtrim((string) getenv('CROOGO_URL'), '/') . $path;
    }

    protected function visit(string $path): Crawler
    {
        return $this->browser->request('GET', $this->url($path));
    }

    protected function statusCode(): int
    {
        return $this->browser->getInternalResponse()->getStatusCode();
    }

    protected function title(): string
    {
        return trim($this->browser->getCrawler()->filter('title')->text());
    }

    /** Visible text of the current page, whitespace collapsed. */
    protected function pageText(): string
    {
        return $this->browser->getCrawler()->filter('body')->text();
    }

    protected function loginAsAdmin(): void
    {
        $form = $this->visit('/users/login')->filter('#UserLoginForm')->form([
            'data[User][username]' => 'admin',
            'data[User][password]' => 'password',
        ]);
        $this->browser->submit($form);
    }

    /** A value no earlier run has used, so tests don't depend on database state. */
    protected function unique(string $prefix): string
    {
        return $prefix . ' ' . bin2hex(random_bytes(4));
    }

    protected function slug(string $title): string
    {
        return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    }
}
