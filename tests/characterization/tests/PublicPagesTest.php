<?php

declare(strict_types=1);

namespace Characterization;

final class PublicPagesTest extends CroogoTestCase
{
    public function testHomePageListsPromotedPostsAndMainMenu(): void
    {
        $page = $this->visit('/');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Croogo', $page->filter('h1.site-title')->text());
        $this->assertGreaterThan(0, $page->filter('#content a[href="/blog/hello-world"]')->count());
        $this->assertSame(['Home', 'About', 'Contact'], $page->filter('#nav .menu li a')->each(fn ($a) => $a->text()));
    }

    public function testBlogPostShowsContentAndCommentForm(): void
    {
        $page = $this->visit('/blog/hello-world');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Hello World » Croogo', $this->title());
        $this->assertStringContainsString('Welcome to Croogo. This is your first post.', $this->pageText());
        $this->assertCount(1, $page->filter('form[action="/comments/add/1"]'));
    }

    public function testStaticPage(): void
    {
        $this->visit('/about');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('About » Croogo', $this->title());
    }

    public function testSearchFindsPostByWord(): void
    {
        $page = $this->visit('/search/q:hello');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Search Results: hello » Croogo', $this->title());
        $this->assertGreaterThan(0, $page->filter('#content a[href="/blog/hello-world"]')->count());
    }

    public function testPromotedPostsRssFeed(): void
    {
        $this->browser->request('GET', $this->url('/nodes/promoted.rss'));
        $body = $this->browser->getInternalResponse()->getContent();

        $this->assertSame(200, $this->statusCode());
        $this->assertStringContainsString('<rss', $body);
        $this->assertStringContainsString('Hello World', $body);
    }

    /** Recorded as-is: unknown addresses answer 200 with an error page, not 404. */
    public function testUnknownAddressAnswers200WithErrorPage(): void
    {
        $this->visit('/no-such-page');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Errors » Croogo', $this->title());
        $this->assertStringContainsString('The requested address was not found on this server.', $this->pageText());
    }
}
