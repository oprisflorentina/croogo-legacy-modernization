<?php

declare(strict_types=1);

namespace Characterization;

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
        $this->submit($form);

        try {
            $this->visit('/blog/' . $slug);
            $this->assertSame($title . ' » Croogo', $this->title());
            $this->assertStringContainsString('Body of ' . $title, $this->pageText());

            $newTitle = $title . ' edited';
            $form = $this->browser->click($this->adminNodeLink($title, 'edit'))->selectButton('Save')->form([
                'data[Node][title]' => $newTitle,
            ]);
            $this->submit($form);

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

    public function testVisitorCommentIsPublishedImmediately(): void
    {
        $body = $this->unique('Characterization comment');

        $form = $this->visit('/blog/hello-world')->selectButton('Post comment')->form([
            'data[Comment][name]' => 'Visitor',
            'data[Comment][email]' => 'visitor@example.com',
            'data[Comment][website]' => 'http://example.com',
            'data[Comment][body]' => $body,
        ]);
        $this->submit($form);

        $this->visit('/blog/hello-world');
        $this->assertStringContainsString($body, $this->pageText());
    }

    /**
     * The admin list shows a file's type and, in brackets, its extension. A
     * stored name without a dot used to show in full as its "extension":
     * FilemanagerHelper::filename2ext() compared an array with 1.
     */
    public function testAttachmentWithoutAnExtensionIsListedAsFile(): void
    {
        $title = 'notes-' . bin2hex(random_bytes(4));
        $file = sys_get_temp_dir() . '/' . $title . '.txt';
        file_put_contents($file, 'Plain text.');

        $this->loginAsAdmin();
        $this->uploadAttachment($file);
        $this->assertIsArray($this->storedAttachment($title), 'The attachment should be saved.');

        // New uploads always keep an extension, so the stored name is changed
        // to stand for an older upload without one.
        $rename = $this->db()->prepare("UPDATE nodes SET slug = ? WHERE type = 'attachment' AND title = ?");
        $rename->execute([$title, $title]);
        try {
            $row = $this->visit('/admin/attachments')->filter('tr')->reduce(fn ($tr) => str_contains($tr->text(), $title));
            $this->assertStringContainsString('text/plain (file)', $row->text());
        } finally {
            // Put the real name back, so the delete removes the file.
            $rename->execute([basename($file), $title]);
            unlink($file);
            $this->deleteAttachment(basename($file));
        }
    }
}
