<?php

declare(strict_types=1);

namespace Characterization;

/**
 * Croogo's page layout around two placeholders, for the parts of the site
 * that moved to Symfony. Symfony fetches it and puts its own title and
 * content in place of the placeholders (transclusion), so a moved page keeps
 * the same header, menu and sidebar without a second copy of the layout.
 */
final class LegacyLayoutTest extends CroogoTestCase
{
    private const TITLE = '%%LEGACY_LAYOUT_TITLE%%';
    private const CONTENT = '<!--%%LEGACY_LAYOUT_CONTENT%%-->';

    public function testTheLayoutSurroundsThePlaceholders(): void
    {
        $page = $this->visit('/legacy-layout?path=' . urlencode('/contact'));

        $this->assertSame(200, $this->statusCode());
        $this->assertSame(self::TITLE . ' » Croogo', $this->title());
        $this->assertStringContainsString(self::CONTENT, $page->filter('#content')->html());
        $this->assertSame('noindex', $this->browser->getInternalResponse()->getHeader('X-Robots-Tag'));
        foreach (['About', 'Categories', 'Recent Posts', 'Meta'] as $block) {
            $this->assertStringContainsString($block, $page->filter('#sidebar')->text());
        }
    }

    /** The main menu marks the page the layout is for, as it would on that page itself. */
    public function testTheMenuMarksThePageItIsFor(): void
    {
        $this->visit('/legacy-layout?path=' . urlencode('/contact'));
        $this->assertSame(['/contact'], $this->selectedMenuLinks());

        $this->visit('/legacy-layout?path=' . urlencode('/about'));
        $this->assertSame(['/about'], $this->selectedMenuLinks());
    }

    /** Anything but a plain path on this site renders for the home page. */
    public function testOnlyPlainSitePathsAreAccepted(): void
    {
        foreach (['//evil.example/contact', 'javascript:alert(1)', '/contact"><script>', ''] as $path) {
            $this->visit('/legacy-layout?path=' . urlencode($path));
            $this->assertSame(['/'], $this->selectedMenuLinks(), "Path '$path'");
        }
    }

    /**
     * A block can be limited to some pages. The layout shows it only when it's
     * built for one of them, as Croogo shows it only on those pages itself.
     * The sample "About" block (id 3) is limited to the contact page here.
     */
    public function testBlocksFollowThePageTheyAreFor(): void
    {
        $this->setBlockPaths(3, 'contact');

        try {
            $this->visit('/legacy-layout?path=' . urlencode('/contact'));
            $this->assertCount(1, $this->browser->getCrawler()->filter('#sidebar #block-3'), 'Shown for /contact');

            $this->visit('/legacy-layout?path=' . urlencode('/about'));
            $this->assertCount(0, $this->browser->getCrawler()->filter('#sidebar #block-3'), 'Hidden for /about');
        } finally {
            $this->setBlockPaths(3, '');
        }
    }

    /** Sets a block's "Visibility paths" through the admin, which also clears Croogo's block cache. */
    private function setBlockPaths(int $blockId, string $paths): void
    {
        $this->browser->getCookieJar()->clear();
        $this->loginAsAdmin();
        $form = $this->visit('/admin/blocks/edit/' . $blockId)->selectButton('Save')->form([
            'data[Block][visibility_paths]' => $paths,
        ]);
        $this->submit($form);
        $this->assertStringContainsString('The Block has been saved', $this->pageText());
        $this->browser->getCookieJar()->clear();
    }

    /** @return list<string> */
    private function selectedMenuLinks(): array
    {
        return $this->browser->getCrawler()->filter('#nav a.selected')->each(fn ($a) => (string) $a->attr('href'));
    }
}
