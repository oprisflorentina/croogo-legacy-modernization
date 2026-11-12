<?php

declare(strict_types=1);

namespace Characterization;

use Symfony\Component\HttpClient\HttpClient;

/**
 * Croogo 1.3 stored passwords as sha1(Security.salt . password). New
 * passwords are stored with password_hash(). Users who still have an old
 * SHA1 hash get it checked one last time at login and replaced on success,
 * so nobody has to reset their password.
 *
 * These tests read the users table directly: the stored hash is the thing
 * under test. The table stays the same whichever app serves the site.
 */
final class PasswordHashingTest extends CroogoTestCase
{
    /**
     * CakePHP's published salt, which the sample data's hashes were made with.
     * The site now has its own salt, and compose.yaml passes this one as
     * CROOGO_LEGACY_SALT, so these tests also cover a changed salt.
     */
    private const SALT = 'DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi19';

    /**
     * PASSWORD_DEFAULT can move to a stronger algorithm with a longer hash in
     * a future PHP version. PHP's documentation recommends room for 255
     * characters; a shorter column would cut such hashes off.
     */
    public function testThePasswordColumnHasRoomForFutureHashes(): void
    {
        $query = $this->db()->query(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password'"
        );

        $this->assertSame(255, (int) $query->fetchColumn());
    }

    public function testAnOldSha1HashIsReplacedAtTheFirstLogin(): void
    {
        $password = 'secret-' . bin2hex(random_bytes(4));
        $username = $this->createUserWithOldHash($password);

        try {
            $this->logIn($username, 'not-' . $password);
            $this->assertSame(200, $this->statusCode(), 'A wrong password must not log in.');
            $this->assertSame(sha1(self::SALT . $password), $this->storedHash($username), 'A failed login must leave the hash alone.');

            $this->logIn($username, $password);
            $this->assertLoggedIn();
            $hash = $this->storedHash($username);
            $this->assertModernHashOf($password, $hash);

            // The new hash works on its own, and a current hash is not rewritten.
            $this->logIn($username, $password);
            $this->assertLoggedIn();
            $this->assertSame($hash, $this->storedHash($username));
        } finally {
            $this->deleteUser($username);
        }
    }

    public function testAdminPasswordResetChecksAnOldHashAndStoresANewOne(): void
    {
        $password = 'secret-' . bin2hex(random_bytes(4));
        $newPassword = 'new-' . $password;
        $username = $this->createUserWithOldHash($password);

        try {
            $this->loginAsAdmin();
            $form = $this->visit('/admin/users/reset_password/' . $this->userId($username))->selectButton('Reset')->form([
                'data[User][current_password]' => $password,
                'data[User][password]' => $newPassword,
            ]);
            $this->submit($form);
            $this->assertStringContainsString('Password has been reset.', $this->pageText());
            $this->assertModernHashOf($newPassword, $this->storedHash($username));

            $this->logIn($username, $newPassword);
            $this->assertLoggedIn();
        } finally {
            $this->deleteUser($username);
        }
    }

    /**
     * Both resets used to validate the new password after it was hashed, so
     * "at least 6 characters" never failed. A refused reset keeps the old hash.
     */
    public function testBothResetsRefuseAShortPassword(): void
    {
        $password = 'secret-' . bin2hex(random_bytes(4));
        $username = $this->createUser('short', $password);
        $hash = $this->storedHash($username);

        try {
            $this->loginAsAdmin();
            $form = $this->visit('/admin/users/reset_password/' . $this->userId($username))->selectButton('Reset')->form([
                'data[User][current_password]' => $password,
                'data[User][password]' => 'abc',
            ]);
            $this->submit($form);
            $this->assertStringContainsString('Passwords must be at least 6 characters long.', $this->pageText(), 'Admin reset');
            $this->assertSame($hash, $this->storedHash($username), 'Admin reset: the hash should not change.');

            $this->browser->getCookieJar()->clear();
            $form = $this->visit('/users/forgot')->selectButton('Submit')->form(['data[User][username]' => $username]);
            $this->submit($form);
            $key = $this->column('activation_key', $username);
            $form = $this->visit('/users/reset/' . $username . '/' . $key)->selectButton('Submit')->form([
                'data[User][password]' => 'abc',
            ]);
            $this->submit($form);
            $this->assertStringContainsString('Passwords must be at least 6 characters long.', $this->pageText(), 'Forgotten password');
            $this->assertSame($hash, $this->storedHash($username), 'Forgotten password: the hash should not change.');
        } finally {
            $this->deleteUser($username);
        }
    }

    public function testForgottenPasswordResetStoresAModernHash(): void
    {
        $username = $this->createUserWithOldHash('secret-' . bin2hex(random_bytes(4)));
        $newPassword = 'new-secret-' . bin2hex(random_bytes(4));

        try {
            $form = $this->visit('/users/forgot')->selectButton('Submit')->form([
                'data[User][username]' => $username,
            ]);
            $this->submit($form);
            $this->assertStringContainsString('An email has been sent with instructions for resetting your password.', $this->pageText());
            $this->assertSame('[Croogo] Reset Password', $this->lastEmailSubjectTo($username . '@example.com'));

            // The emailed link carries the user's activation key.
            $key = $this->column('activation_key', $username);
            $form = $this->visit('/users/reset/' . $username . '/' . $key)->selectButton('Submit')->form([
                'data[User][password]' => $newPassword,
            ]);
            $this->submit($form);
            $this->assertStringContainsString('Your password has been reset successfully.', $this->pageText());
            $this->assertModernHashOf($newPassword, $this->storedHash($username));

            $this->logIn($username, $newPassword);
            $this->assertLoggedIn();
        } finally {
            $this->deleteUser($username);
        }
    }

    /**
     * The user is created through the normal form, then the hash is swapped
     * for the one Croogo 1.3 would have stored, as if the user had registered
     * before the upgrade.
     */
    private function createUserWithOldHash(string $password): string
    {
        $username = $this->createUser('legacy', 'temporary-' . $password);

        $this->db()->prepare('UPDATE users SET password = ? WHERE username = ?')
            ->execute([sha1(self::SALT . $password), $username]);
        $this->assertSame(sha1(self::SALT . $password), $this->storedHash($username));

        return $username;
    }

    /** Logs in from a fresh session and stops at the redirect. */
    private function logIn(string $username, string $password): void
    {
        $this->browser->getCookieJar()->clear();
        $form = $this->visit('/users/login')->filter('#UserLoginForm')->form([
            'data[User][username]' => $username,
            'data[User][password]' => $password,
        ]);
        $this->browser->followRedirects(false);
        try {
            $this->submit($form);
        } finally {
            $this->browser->followRedirects(true);
        }
    }

    private function assertLoggedIn(): void
    {
        $this->assertSame(302, $this->statusCode(), 'The login should succeed.');
        $this->assertSame($this->url('/users'), $this->browser->getInternalResponse()->getHeader('Location'));
    }

    private function assertModernHashOf(string $password, string $hash): void
    {
        $this->assertNotNull(password_get_info($hash)['algo'], 'Expected a password_hash() hash, got: ' . $hash);
        $this->assertTrue(password_verify($password, $hash), 'The stored hash does not match the password.');
    }

    private function storedHash(string $username): string
    {
        return $this->column('password', $username);
    }

    private function userId(string $username): string
    {
        return $this->column('id', $username);
    }

    private function column(string $column, string $username): string
    {
        $query = $this->db()->prepare(sprintf('SELECT `%s` FROM users WHERE username = ?', $column));
        $query->execute([$username]);

        return (string) $query->fetchColumn();
    }

    private function lastEmailSubjectTo(string $address): string
    {
        $found = HttpClient::create()
            ->request('GET', rtrim((string) getenv('MAILPIT_URL'), '/') . '/api/v1/search', [
                'query' => ['query' => 'to:"' . $address . '"'],
            ])
            ->toArray();
        $this->assertGreaterThan(0, $found['messages_count'], 'No email was sent to ' . $address);

        return $found['messages'][0]['Subject'];
    }
}
