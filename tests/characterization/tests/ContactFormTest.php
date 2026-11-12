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

    /**
     * The form used to run htmlspecialchars() on the name, subject and message
     * before validating and emailing them, so the plain-text email showed
     * &amp; and &lt; instead of what was typed, like comments did (security
     * finding 2). The name also goes in the From header, quoted, so a name
     * that looks like an address can't take the visitor's place as sender.
     */
    public function testEmailShowsTheMessageAsTyped(): void
    {
        $marker = $this->unique('contact-typed');
        $name = 'O\'Brien & "Sons" <boss@your-site.com>';
        $this->sendContactMessage($name, 'visitor@example.com', 'Price < 5 & "cheap"', '<b>Bold</b> & ' . $marker);

        $email = $this->onlyEmailContaining($marker);
        $this->assertStringContainsString("Name: $name\n", $email['Text']);
        $this->assertStringContainsString("Subject: Price < 5 & \"cheap\"\n", $email['Text']);
        $this->assertStringContainsString('Message: <b>Bold</b> & ' . $marker, $email['Text']);
        $this->assertSame('visitor@example.com', $email['From']['Address'], 'The visitor stays the sender.');
        $this->assertSame($name, $email['From']['Name']);
    }

    /**
     * With archiving switched on, the message is also stored. It's stored as
     * typed, and the admin's message list escapes it when showing it.
     */
    public function testArchivedMessageIsStoredAsTypedAndEscapedInTheAdmin(): void
    {
        $marker = $this->unique('contact-archive');
        $name = '<i>' . $marker . '</i>';
        $this->setMessageArchive(true);

        try {
            $this->browser->getCookieJar()->clear();
            $this->sendContactMessage($name, 'visitor@example.com', '<b>Subject</b>', 'Hello');
            $this->assertStringContainsString('Your message has been received...', $this->pageText());

            $query = $this->db()->prepare('SELECT name, title FROM messages WHERE name LIKE ?');
            $query->execute(['%' . $marker . '%']);
            $this->assertSame(['name' => $name, 'title' => '<b>Subject</b>'], $query->fetch(\PDO::FETCH_ASSOC));

            $this->loginAsAdmin();
            $row = $this->visit('/admin/messages')->filter('tr')->reduce(fn ($tr) => str_contains($tr->text(), $marker));
            $this->assertStringContainsString($name, $row->text(), 'The name should show as typed.');
            $this->assertStringContainsString('<b>Subject</b>', $row->text(), 'The subject should show as typed.');
            $this->assertCount(0, $row->filter('i, b'), 'The tags should be text, not HTML.');
        } finally {
            $this->db()->prepare('DELETE FROM messages WHERE name LIKE ?')->execute(['%' . $marker . '%']);
            $this->setMessageArchive(false);
        }
    }

    /** Switches "Save messages in database" for the sample contact form, through the admin. */
    private function setMessageArchive(bool $on): void
    {
        $this->browser->getCookieJar()->clear();
        $this->loginAsAdmin();
        $form = $this->visit('/admin/contacts/edit/1')->selectButton('Save')->form();
        $on ? $form['data[Contact][message_archive]']->tick() : $form['data[Contact][message_archive]']->untick();
        $this->submit($form);
        $this->assertStringContainsString('The Contact has been saved', $this->pageText());
        $this->browser->getCookieJar()->clear();
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
