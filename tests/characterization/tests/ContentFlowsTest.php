<?php

declare(strict_types=1);

namespace Characterization;

use Symfony\Component\HttpClient\HttpClient;

final class ContentFlowsTest extends CroogoTestCase
{
    public function testAdminCreatesAndEditsABlogPost(): void
    {
        $this->loginAsAdmin();
        $title = $this->unique('Characterization post');
        $slug = $this->slug($title);

        $form = $this->visit('/admin/nodes/add/blog')->selectButton('Save')->form([
            'data[Node][title]' => $title,
            'data[Node][slug]' => $slug,
            'data[Node][body]' => 'Body of ' . $title,
        ]);
        // Not promoted, so test posts never push the sample post off the home page.
        $form['data[Node][promote]']->untick();
        $this->browser->submit($form);

        try {
            $this->visit('/blog/' . $slug);
            $this->assertSame($title . ' » Croogo', $this->title());
            $this->assertStringContainsString('Body of ' . $title, $this->pageText());

            $newTitle = $title . ' edited';
            $form = $this->browser->click($this->adminNodeLink($title, 'edit'))->selectButton('Save')->form([
                'data[Node][title]' => $newTitle,
            ]);
            $this->browser->submit($form);

            $this->visit('/blog/' . $slug);
            $this->assertSame($newTitle . ' » Croogo', $this->title());
        } finally {
            $this->browser->click($this->adminNodeLink($title, 'delete'));
        }

        // A deleted post's address redirects to the home page.
        $this->browser->followRedirects(false);
        $this->visit('/blog/' . $slug);
        $this->assertSame(302, $this->statusCode());
        $this->assertSame($this->url('/'), $this->browser->getInternalResponse()->getHeader('Location'));
    }

    private function adminNodeLink(string $title, string $action): \Symfony\Component\DomCrawler\Link
    {
        return $this->visit('/admin/nodes')
            ->filter('tr')->reduce(fn ($row) => str_contains($row->text(), $title))
            ->filter('a[href*="/admin/nodes/' . $action . '/"]')->link();
    }

    /**
     * The sample contact form emails the site owner and doesn't archive the
     * message (message_archive = 0), so the email is the only record of it.
     */
    public function testVisitorContactMessageIsEmailedToTheSiteOwner(): void
    {
        $marker = $this->unique('characterization-contact');

        $form = $this->visit('/contact')->selectButton('Send')->form([
            'data[Message][name]' => 'Visitor',
            'data[Message][email]' => 'visitor@example.com',
            'data[Message][title]' => 'Question',
            'data[Message][body]' => 'Hello ' . $marker,
        ]);
        $this->browser->submit($form);
        $this->assertStringContainsString('Your message has been received...', $this->pageText());

        $found = HttpClient::create()
            ->request('GET', rtrim((string) getenv('MAILPIT_URL'), '/') . '/api/v1/search', [
                'query' => ['query' => '"' . $marker . '"'],
            ])
            ->toArray();
        $this->assertSame(1, $found['messages_count']);
        $this->assertSame('[Croogo] Contact', $found['messages'][0]['Subject']);
        $this->assertSame('you@your-site.com', $found['messages'][0]['To'][0]['Address']);
    }

    public function testVisitorCommentIsPublishedImmediately(): void
    {
        $body = $this->unique('Characterization comment');

        $form = $this->visit('/blog/hello-world')->selectButton('Post comment')->form([
            'data[Comment][name]' => 'Visitor',
            'data[Comment][email]' => 'visitor@example.com',
            'data[Comment][website]' => 'http://example.com',
            'data[Comment][body]' => $body,
        ]);
        $this->browser->submit($form);

        $this->visit('/blog/hello-world');
        $this->assertStringContainsString($body, $this->pageText());
    }
}
