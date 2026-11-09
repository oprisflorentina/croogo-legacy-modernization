<?php

declare(strict_types=1);

namespace Characterization;

use PHPUnit\Framework\Attributes\Group;

/**
 * Behavior that is wrong but real. These tests pin it down so that each fix
 * in the PHP 8.3 step shows up as a deliberate change: the fix commit flips
 * the matching test from "is vulnerable" to "is safe".
 */
#[Group('known-issue')]
final class KnownIssuesTest extends CroogoTestCase
{
    /** Security finding 2, fixed: a javascript: address is refused as the commenter's website. */
    public function testCommentWebsiteRejectsJavascriptUrl(): void
    {
        $body = $this->unique('website check');

        $this->postComment('Visitor', 'javascript:alert(' . random_int(100000, 999999) . ')', $body);

        $this->assertStringContainsString('Please enter a web address starting with http:// or https://', $this->pageText());
        $this->visit('/blog/hello-world');
        $this->assertStringNotContainsString($body, $this->pageText(), 'The comment should not have been saved.');
    }

    /**
     * Security finding 2, fixed: comments saved before the check existed can
     * still hold a javascript: address. The name is then shown without a link.
     */
    public function testStoredJavascriptWebsiteIsNotLinked(): void
    {
        $body = $this->unique('stored website check');
        // Croogo caches each post's comments, and saving a comment clears that
        // cache. The redirect back to the post isn't followed, so nothing is
        // cached again before the row is changed behind the app's back.
        $this->browser->followRedirects(false);
        try {
            $this->postComment('Visitor', 'http://example.com', $body);
        } finally {
            $this->browser->followRedirects(true);
        }
        $this->db()->prepare('UPDATE comments SET website = ? WHERE body = ?')
            ->execute(['javascript:alert(' . random_int(100000, 999999) . ')', $body]);
        $id = $this->db()->prepare('SELECT id FROM comments WHERE body = ?');
        $id->execute([$body]);

        $page = $this->visit('/blog/hello-world');
        $name = $page->filter('#comment-' . $id->fetchColumn() . ' .comment-info .name');
        $this->assertSame('Visitor', trim($name->text()));
        $this->assertCount(0, $name->filter('a'));
        $this->assertCount(0, $page->filter('a[href^="javascript:"]'), 'No comment on the page may link to a javascript: address.');
    }

    /**
     * Fixed with finding 2: comment names and bodies are stored as typed and
     * escaped where they are shown. They used to be escaped before saving,
     * so pages that escape again showed them double-encoded.
     */
    public function testCommentTextIsStoredAsTypedAndEscapedWhenShown(): void
    {
        // With a website the name is a link, without one it is plain text.
        foreach (['http://example.com', ''] as $website) {
            $marker = bin2hex(random_bytes(4));
            $name = '<i>' . $marker . '</i> & Co';
            $body = '<b>' . $marker . '</b> "quoted" & more';

            $this->postComment($name, $website, $body);

            $query = $this->db()->prepare('SELECT id, name, body FROM comments WHERE body = ?');
            $query->execute([$body]);
            $stored = $query->fetch(\PDO::FETCH_ASSOC);
            $this->assertIsArray($stored, 'No comment is stored with the body exactly as typed.');
            $this->assertSame($name, $stored['name']);

            $shown = $this->visit('/blog/hello-world')->filter('#comment-' . $stored['id']);
            $this->assertSame($name, trim($shown->filter('.name')->text()));
            $this->assertSame($body, trim($shown->filter('.comment-body')->text()));
            $this->assertCount(0, $shown->filter('.name i, .comment-body b'), 'Markup in a comment must not become HTML.');
        }

        // The admin list shows the name as text and the body in a tooltip.
        $this->loginAsAdmin();
        $row = $this->visit('/admin/comments')->filter('tr')->reduce(fn ($tr) => str_contains($tr->text(), $marker));
        $this->assertCount(1, $row);
        $this->assertStringContainsString($name, $row->text());
        $this->assertCount(0, $row->filter('td i'));
        $this->assertSame($body, $row->filter('a.tooltip')->attr('title'));
    }

    /** Security finding 4: an attachment named .php is saved in the web root and runs. */
    public function testAttachmentUploadRunsPhpCode(): void
    {
        $name = 'probe-' . bin2hex(random_bytes(4)) . '.php';
        $file = sys_get_temp_dir() . '/' . $name;
        file_put_contents($file, '<?php echo "php-exec-" . (6 * 7);');

        $this->loginAsAdmin();
        try {
            $form = $this->visit('/admin/attachments/add')->filter('#NodeAdminAddForm')->form();
            $form['data[Node][file]']->upload($file);
            $this->submit($form);

            $this->browser->request('GET', $this->url('/uploads/' . $name));
            $this->assertSame('php-exec-42', $this->browser->getInternalResponse()->getContent());
        } finally {
            unlink($file);
            $this->deleteAttachment($name);
        }
    }

    /**
     * Since CakePHP 1.3.18 form tokens include the target URL, and the File
     * Manager posts to absolute URLs, so every one of its forms is rejected.
     */
    public function testFileManagerFormsAreRejected(): void
    {
        $this->loginAsAdmin();

        $form = $this->visit('/admin/filemanager/create_file?path=' . urlencode('/var/www/html/app/tmp/'))
            ->selectButton('Create')->form(['data[Filemanager][name]' => 'probe.txt']);
        $this->submit($form);

        $this->assertStringContainsString('Security Error', $this->pageText());
    }

    private function postComment(string $name, string $website, string $body): void
    {
        $form = $this->visit('/blog/hello-world')->selectButton('Post comment')->form([
            'data[Comment][name]' => $name,
            'data[Comment][email]' => 'visitor@example.com',
            'data[Comment][website]' => $website,
            'data[Comment][body]' => $body,
        ]);
        $this->submit($form);
    }

    private function deleteAttachment(string $name): void
    {
        // The list shows the attachment's title: the file name without its extension.
        $title = pathinfo($name, PATHINFO_FILENAME);
        $delete = $this->visit('/admin/attachments')
            ->filter('tr')->reduce(fn ($row) => str_contains($row->text(), $title))
            ->filter('a[href*="/admin/attachments/delete/"]');
        if ($delete->count() > 0) {
            $this->browser->click($delete->link());
        }
    }
}
