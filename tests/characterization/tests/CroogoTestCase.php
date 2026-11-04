<?php

declare(strict_types=1);

namespace Characterization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Base for all characterization tests: a real browser-like client that only
 * talks HTTP, so the same tests run against any server that serves the site.
 *
 * Every page a test loads is also checked for PHP errors. The app runs with
 * debug on, so CakePHP prints warnings and notices into the HTML; a page can
 * show the right content and still be failing underneath.
 */
abstract class CroogoTestCase extends TestCase
{
    private const PHP_ERROR = '/(Fatal error|Parse error|Warning \(\d+\)|Notice \(\d+\)|Deprecated \(\d+\))/';

    protected HttpBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = new class (HttpClient::create()) extends HttpBrowser {
            /** @var list<array{string, string}> */
            public array $pages = [];

            protected function doRequest(object $request): Response
            {
                $response = parent::doRequest($request);
                $this->pages[] = [$request->getUri(), $response->getContent()];

                return $response;
            }
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->browser->pages as [$uri, $body]) {
            // CakePHP prints "<b>Warning</b> (2)", so match on the text.
            $text = strip_tags($body);
            if (preg_match(self::PHP_ERROR, $text, $m)) {
                $this->fail(sprintf('PHP error on %s: "%s"', $uri, trim(substr($text, (int) strpos($text, $m[1]), 200))));
            }
        }
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

    /**
     * Submits a form the way a browser does.
     *
     * CakePHP pairs a hidden empty input with each multi-select and checkbox
     * of the same name, so a browser always sends a value even when nothing
     * is selected. DomCrawler keeps only one field per name and drops the
     * hidden one, so its values are put back here wherever nothing overrides
     * them.
     */
    protected function submit(Form $form): Crawler
    {
        $hidden = [];
        foreach ($form->getFormNode()->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('type') === 'hidden' && $input->getAttribute('name') !== '') {
                parse_str(urlencode($input->getAttribute('name')) . '=' . urlencode($input->getAttribute('value')), $value);
                $hidden = array_replace_recursive($hidden, $value);
            }
        }

        return $this->browser->request(
            $form->getMethod(),
            $form->getUri(),
            array_replace_recursive($hidden, $form->getPhpValues()),
            $form->getPhpFiles(),
        );
    }

    protected function loginAsAdmin(): void
    {
        $form = $this->visit('/users/login')->filter('#UserLoginForm')->form([
            'data[User][username]' => 'admin',
            'data[User][password]' => 'password',
        ]);
        $this->submit($form);
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
