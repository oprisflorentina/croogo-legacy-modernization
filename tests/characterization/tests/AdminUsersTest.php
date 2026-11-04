<?php

declare(strict_types=1);

namespace Characterization;

/**
 * Added after PHPStan flagged the regexes behind AppModel::_validAlias() and
 * _validName() as invalid under PCRE2 (PHP 7.3+). Those rules validate the
 * username and name of every user, so on PHP 8 no user could be saved.
 */
final class AdminUsersTest extends CroogoTestCase
{
    public function testAdminCreatesAUserWhoCanLogIn(): void
    {
        $this->loginAsAdmin();
        $username = 'user' . bin2hex(random_bytes(4));
        $password = 'secret-' . $username;

        $form = $this->visit('/admin/users/add')->selectButton('Save')->form([
            'data[User][role_id]' => '2',
            'data[User][username]' => $username,
            'data[User][password]' => $password,
            'data[User][name]' => 'Test User',
            'data[User][email]' => $username . '@example.com',
        ]);
        $form['data[User][status]']->tick();
        $this->submit($form);

        try {
            $this->assertCount(1, $this->userRow($username), 'The new user should be listed.');

            // A fresh session, then the new user logs in.
            $this->browser->getCookieJar()->clear();
            $this->browser->followRedirects(false);
            $form = $this->visit('/users/login')->filter('#UserLoginForm')->form([
                'data[User][username]' => $username,
                'data[User][password]' => $password,
            ]);
            $this->submit($form);
            $this->assertSame(302, $this->statusCode());
            $this->assertSame($this->url('/users'), $this->browser->getInternalResponse()->getHeader('Location'));
        } finally {
            $this->browser->followRedirects(true);
            $this->browser->getCookieJar()->clear();
            $this->loginAsAdmin();
            $delete = $this->userRow($username)->filter('a[href*="/admin/users/delete/"]');
            if ($delete->count() > 0) {
                $this->browser->click($delete->link());
            }
        }
    }

    private function userRow(string $username): \Symfony\Component\DomCrawler\Crawler
    {
        return $this->visit('/admin/users')->filter('tr')->reduce(fn ($tr) => str_contains($tr->text(), $username));
    }
}
