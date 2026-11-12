<?php

declare(strict_types=1);

namespace Characterization;

/**
 * Opens every admin screen reachable from the admin menu (two clicks deep).
 * There are no assertions about content here: the base class fails the test
 * if any of those pages shows a PHP error.
 */
final class AdminPagesTest extends CroogoTestCase
{
    /**
     * Links that change data through a plain GET request (deletes, toggles,
     * reordering, theme and plugin activation, ACL regeneration) or end the
     * session. A crawler must never follow them.
     */
    private const UNSAFE = '#delete|logout|toggle|activate|move(up|down)|process|generate|/token:#';

    public function testEveryAdminScreenRendersWithoutPhpErrors(): void
    {
        $this->loginAsAdmin();

        $seen = [];
        $queue = ['/admin/nodes'];
        for ($depth = 0; $depth < 3 && $queue !== []; $depth++) {
            $next = [];
            foreach ($queue as $path) {
                if (isset($seen[$path]) || preg_match(self::UNSAFE, $path)) {
                    continue;
                }
                $seen[$path] = true;
                $links = $this->visit($path)->filter('a[href^="/admin"]')->each(fn ($a) => (string) $a->attr('href'));
                foreach ($links as $href) {
                    $next[] = strtok($href, '#');
                }
            }
            $queue = array_values(array_unique($next));
        }

        $this->assertGreaterThan(100, count($seen), 'The crawl should reach the whole admin.');
    }
}
