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
    /** Security finding 2: the commenter's website becomes a raw link href. */
    public function testCommentWebsiteAcceptsJavascriptUrl(): void
    {
        $payload = 'javascript:alert(' . random_int(100000, 999999) . ')';

        $this->postComment('Visitor', $payload, $this->unique('website check'));

        $this->assertCount(1, $this->visit('/blog/hello-world')->filter('a[href="' . $payload . '"]'));
    }

    /** Escaping happens before saving, so output that escapes again shows entities. */
    public function testCommentNameIsEncodedTwice(): void
    {
        $marker = bin2hex(random_bytes(4));

        $this->postComment('<b>' . $marker . '</b>', 'http://example.com', $this->unique('name check'));

        $this->visit('/blog/hello-world');
        $this->assertStringContainsString('&lt;b&gt;' . $marker . '&lt;/b&gt;', $this->pageText());
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
