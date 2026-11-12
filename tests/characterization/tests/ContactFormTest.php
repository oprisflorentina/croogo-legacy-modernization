<?php

declare(strict_types=1);

namespace Characterization;

use Symfony\Component\HttpClient\HttpClient;

/**
 * The public contact form at /contact. These tests are the proof for moving
 * it to Symfony: they must pass unchanged against both apps.
 */
final class ContactFormTest extends CroogoTestCase
{
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
        $this->submit($form);
        $this->assertStringContainsString('Your message has been received...', $this->pageText());

        $found = $this->emailsContaining($marker);
        $this->assertSame(1, $found['messages_count']);
        $this->assertSame('[Croogo] Contact', $found['messages'][0]['Subject']);
        $this->assertSame('you@your-site.com', $found['messages'][0]['To'][0]['Address']);
    }

    /** Mailpit's search for a text in the caught emails. */
    private function emailsContaining(string $text): array
    {
        return HttpClient::create()
            ->request('GET', rtrim((string) getenv('MAILPIT_URL'), '/') . '/api/v1/search', [
                'query' => ['query' => '"' . $text . '"'],
            ])
            ->toArray();
    }
}
