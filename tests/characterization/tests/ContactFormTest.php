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

    /**
     * The email is plain text: a link back to the form, then one line per
     * field, with the visitor's IP address. It comes "from" the visitor.
     */
    public function testEmailListsTheMessageDetails(): void
    {
        $marker = $this->unique('contact-details');
        $this->sendContactMessage('Visitor', 'visitor@example.com', 'Question', 'Hello ' . $marker);

        $email = $this->onlyEmailContaining($marker);
        $this->assertSame('Visitor', $email['From']['Name']);
        $this->assertSame('visitor@example.com', $email['From']['Address']);
        $this->assertStringContainsString('You have received a new message at: ' . $this->url('/contact'), $email['Text']);
        $this->assertStringContainsString("Name: Visitor\n", $email['Text']);
        $this->assertStringContainsString("Email: visitor@example.com\n", $email['Text']);
        $this->assertStringContainsString("Subject: Question\n", $email['Text']);
        $this->assertMatchesRegularExpression('/^IP Address: \d+\.\d+\.\d+\.\d+$/m', $email['Text']);
        $this->assertStringContainsString('Message: Hello ' . $marker, $email['Text']);
    }

    /** Every field is required, the email address has to look like one, and nothing is sent until they pass. */
    public function testMissingFieldsAreShownAndNothingIsSent(): void
    {
        $marker = $this->unique('contact-invalid');
        $this->sendContactMessage('', 'not-an-address', '', 'Hello ' . $marker);

        $this->assertStringNotContainsString('Your message has been received', $this->pageText());
        $this->assertSame(2, substr_count($this->pageText(), 'This field cannot be left blank.'), 'Name and subject');
        $this->assertStringContainsString('Please provide a valid email address.', $this->pageText());
        $this->assertSame(0, $this->emailsContaining($marker)['messages_count']);
    }

    private function sendContactMessage(string $name, string $email, string $subject, string $body): void
    {
        $form = $this->visit('/contact')->selectButton('Send')->form([
            'data[Message][name]' => $name,
            'data[Message][email]' => $email,
            'data[Message][title]' => $subject,
            'data[Message][body]' => $body,
        ]);
        $this->submit($form);
    }

    /**
     * The one caught email containing a text, with its headers and plain-text
     * body. Email lines end in \r\n; the body is normalized to \n so the tests
     * check content, not line endings.
     */
    private function onlyEmailContaining(string $text): array
    {
        $found = $this->emailsContaining($text);
        $this->assertSame(1, $found['messages_count'], 'Expected exactly one email containing ' . $text);

        $email = HttpClient::create()
            ->request('GET', rtrim((string) getenv('MAILPIT_URL'), '/') . '/api/v1/message/' . $found['messages'][0]['ID'])
            ->toArray();
        $email['Text'] = str_replace("\r\n", "\n", $email['Text']);

        return $email;
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
