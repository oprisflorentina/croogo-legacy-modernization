<?php

declare(strict_types=1);

namespace Characterization;

final class AdminAuthTest extends CroogoTestCase
{
    public function testAdminRedirectsToLoginWhenLoggedOut(): void
    {
        $this->browser->followRedirects(false);
        $this->visit('/admin/nodes');

        $this->assertSame(302, $this->statusCode());
        $this->assertStringEndsWith('/admin/users/login', $this->browser->getInternalResponse()->getHeader('Location'));
    }

    /**
     * Uses a fresh username each run: failed attempts are counted per username,
     * and using "admin" here would lock the other tests out (see below).
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
     * Five failures in five minutes block the username, whoever is typing.
     * Anyone who knows a username can lock its owner out.
     */
    public function testFiveFailedLoginsLockTheUsername(): void
    {
        $username = $this->unique('lockout');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->attemptLogin($username, 'wrong');
            $this->assertStringContainsString('Invalid username or password', $this->pageText());
        }

        $this->attemptLogin($username, 'wrong');
        $this->assertStringContainsString('You have reached maximum limit for failed login attempts.', $this->pageText());
    }

    private function attemptLogin(string $username, string $password): void
    {
        $form = $this->visit('/users/login')->filter('#UserLoginForm')->form([
            'data[User][username]' => $username,
            'data[User][password]' => $password,
        ]);
        $this->submit($form);
    }

    public function testAdminCanLogInAndOpenTheContentList(): void
    {
        $this->loginAsAdmin();
        $this->visit('/admin/nodes');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('Content - Croogo', $this->title());
    }
}
