<?php

declare(strict_types=1);

namespace Characterization;

use PHPUnit\Framework\Attributes\Group;

/**
 * These tests first pinned down behavior that was wrong but real, so that
 * each fix in the PHP 8.3 step would show up as a deliberate change: the fix
 * commit flipped the matching test from "is vulnerable" to "is safe". All of
 * them are flipped now, and they keep the security fixes from coming back.
 */
#[Group('known-issue')]
final class KnownIssuesTest extends CroogoTestCase
{
    /** Prints "php-exec-42" only if the server runs it. */
    private const PHP_PROBE = '<?php echo "php-exec-" . (6 * 7);';

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

    /** Security finding 4, fixed: a .php file is refused as an attachment. */
    public function testAttachmentUploadRefusesPhpFile(): void
    {
        $name = 'probe-' . bin2hex(random_bytes(4)) . '.php';
        $file = sys_get_temp_dir() . '/' . $name;
        file_put_contents($file, self::PHP_PROBE);

        $this->loginAsAdmin();
        try {
            $this->uploadAttachment($file);
            $this->assertStringContainsString('This file type is not allowed.', $this->pageText());
            $this->assertFalse($this->storedAttachment(pathinfo($name, PATHINFO_FILENAME)), 'No attachment should be saved.');

            $this->browser->request('GET', $this->url('/uploads/' . $name));
            $this->assertStringNotContainsString('php-exec-42', $this->browser->getInternalResponse()->getContent());
        } finally {
            unlink($file);
            $this->deleteAttachment($name);
        }
    }

    /**
     * Security finding 4, fixed: the stored file name only keeps letters,
     * digits and hyphens, so "x.php.png" can't run on a server that treats
     * any name containing ".php." as code. The type stored is the one the
     * server detects, not the one the browser claims.
     */
    public function testAttachmentGetsASafeNameAndItsRealType(): void
    {
        $title = 'Probe ' . bin2hex(random_bytes(4)) . '.php';
        $file = sys_get_temp_dir() . '/' . $title . '.png';
        file_put_contents($file, self::PHP_PROBE);

        $this->loginAsAdmin();
        try {
            $this->uploadAttachment($file);
            $this->assertStringContainsString('The Attachment has been saved', $this->pageText());

            $stored = $this->storedAttachment($title);
            $this->assertIsArray($stored, 'The attachment should be saved.');
            $this->assertSame('/uploads/' . strtolower(str_replace([' ', '.'], '-', $title)) . '.png', $stored['path']);
            $this->assertSame('text/x-php', $stored['mime_type']);

            // Served as a plain file: the code comes back as text.
            $this->browser->request('GET', $this->url($stored['path']));
            $this->assertSame(self::PHP_PROBE, $this->browser->getInternalResponse()->getContent());
        } finally {
            unlink($file);
            $this->deleteAttachment(basename($file));
        }
    }

    /**
     * Security findings 3 and 5, resolved by removing the File Manager. Its
     * upload took any folder and any file type, and its editor could change
     * any file under app/, PHP code included. Its forms were only rejected
     * because of an unrelated form token bug. Media uploads go through
     * Attachments.
     */
    public function testFileManagerIsGone(): void
    {
        $this->loginAsAdmin();

        $this->visit('/admin/nodes');
        $this->assertCount(0, $this->browser->getCrawler()->filter('a[href*="/admin/filemanager"]'), 'The admin menu should not link to it.');

        foreach (['/admin/filemanager', '/admin/filemanager/editfile?path=' . urlencode('/var/www/html/app/config/core.php')] as $path) {
            $this->visit($path);
            $this->assertSame('Errors » Croogo', $this->title(), $path);
            $this->assertStringContainsString('The requested address was not found on this server.', $this->pageText(), $path);
        }

        $this->visit('/admin/acl/acl_permissions');
        $this->assertStringContainsString('Attachments', $this->pageText(), 'The permissions screen should list the controllers.');
        $this->assertStringNotContainsString('Filemanager', $this->pageText(), 'Its ACL records should be gone.');
    }

    /**
     * Part of security finding 6. CakePHP's web test runner could only fail:
     * SimpleTest isn't bundled, and CakePHP's own test library was never
     * ported to PHP 8. With debug on it printed a parse error with server
     * paths. Now it gets the error page, like any unknown address.
     */
    public function testCakeWebTestRunnerIsGone(): void
    {
        $this->visit('/test.php');

        $this->assertStringContainsString('The requested address was not found on this server.', $this->pageText());
        $this->assertSame('Errors » Croogo', $this->title());
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

    private function uploadAttachment(string $file): void
    {
        $form = $this->visit('/admin/attachments/add')->filter('#NodeAdminAddForm')->form();
        $form['data[Node][file]']->upload($file);
        $this->submit($form);
    }

    /** @return array{path: string, mime_type: string}|false */
    private function storedAttachment(string $title): array|false
    {
        $query = $this->db()->prepare("SELECT path, mime_type FROM nodes WHERE type = 'attachment' AND title = ?");
        $query->execute([$title]);

        return $query->fetch(\PDO::FETCH_ASSOC);
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
