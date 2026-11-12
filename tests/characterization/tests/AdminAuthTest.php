<?php

declare(strict_types=1);

namespace Characterization;

final class AdminAuthTest extends CroogoTestCase
{
    private const LOCKED = 'You have reached maximum limit for failed login attempts.';

    public function testAdminRedirectsToLoginWhenLoggedOut(): void
    {
        $this->browser->followRedirects(false);
        $this->visit('/admin/nodes');

        $this->assertSame(302, $this->statusCode());
        $this->assertStringEndsWith('/admin/users/login', $this->browser->getInternalResponse()->getHeader('Location'));
    }

    /**
     * Uses a fresh username each run: failed attempts are counted per username
     * and address, and every other test logs in from this container's address,
     * so using "admin" here would lock them out (see below).
     */
    public function testWrongPasswordIsRejected(): void
    {
        $this->attemptLogin($this->unique('nobody'), 'not-the-password');

        $this->assertStringContainsString('Invalid username or password', $this->pageText());

        $this->browser->followRedirects(false);
        $this->visit('/admin/nodes');
        $this->assertSame(302, $this->statusCode());
    }

    /**
     * Five failures in five minutes block the username from that address.
     */
    public function testFiveFailedLoginsLockTheUsername(): void
    {
        $username = $this->unique('lockout');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attemptLogin($username, 'wrong');
            $this->assertStringContainsString('Invalid username or password', $this->pageText());
        }

        $this->attemptLogin($username, 'wrong');
        $this->assertStringContainsString(self::LOCKED, $this->pageText());
    }

    /**
     * Security finding 8: the lockout used to block the username for everyone,
     * so anyone who knew it could lock its owner out. Failed attempts now count
     * per username and address. The relay stands for the attacker: the app sees
     * its address instead of this container's.
     */
    public function testFailedLoginsFromAnotherAddressDoNotLockTheOwnerOut(): void
    {
        $password = 'secret-' . bin2hex(random_bytes(4));
        $username = $this->createUser('owner', $password);

        try {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->attemptLogin($username, 'wrong', viaRelay: true);
                $this->assertStringContainsString('Invalid username or password', $this->pageText());
            }
            $this->attemptLogin($username, $password, viaRelay: true);
            $this->assertStringContainsString(self::LOCKED, $this->pageText(), 'The attacker\'s address should be locked out.');

            $this->browser->followRedirects(false);
            $this->attemptLogin($username, $password);
            $this->assertSame(
                $this->url('/users'),
                $this->browser->getInternalResponse()->getHeader('Location'),
                'The owner should still log in from their own address.',
            );
        } finally {
            $this->browser->followRedirects(true);
            $this->deleteUser($username);
        }
    }

    /**
     * The address is the one the connection comes from. Client-IP,
     * ClientAddress and X-Forwarded-For are written by the client, so forging
     * a new address with every attempt must not avoid the lockout. CakePHP's
     * RequestHandler::getClientIP() would believe the first two.
     */
    public function testForgedAddressHeadersDoNotAvoidTheLockout(): void
    {
        $username = $this->unique('lockout');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $forged = '203.0.113.' . $attempt;
            $this->browser->setServerParameter('HTTP_CLIENT_IP', $forged);
            $this->browser->setServerParameter('HTTP_CLIENTADDRESS', $forged);
            $this->browser->setServerParameter('HTTP_X_FORWARDED_FOR', $forged);
            $this->attemptLogin($username, 'wrong');
        }

        $this->assertStringContainsString(self::LOCKED, $this->pageText());
    }

    private function attemptLogin(string $username, string $password, bool $viaRelay = false): void
    {
        $url = $viaRelay ? $this->relayUrl('/users/login') : $this->url('/users/login');
        $form = $this->browser->request('GET', $url)->filter('#UserLoginForm')->form([
            'data[User][username]' => $username,
            'data[User][password]' => $password,
        ]);
        $this->submit($form);
    }

    /** The same site through the relay, a second visitor with its own address. */
    private function relayUrl(string $path): string
    {
        return rtrim((string) getenv('CROOGO_RELAY_URL'), '/') . $path;
    }

    public function testAdminCanLogInAndOpenTheContentList(): void
    {
        $this->loginAsAdmin();
        $this->visit('/admin/nodes');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Content - Croogo', $this->title());
    }
}
