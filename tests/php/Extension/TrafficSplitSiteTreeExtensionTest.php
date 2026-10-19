<?php

namespace SilverStripe\Laboratory\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\TrafficSplitSiteTreeExtension;
use SilverStripe\Laboratory\Model\TrafficSplit;

class TrafficSplitSiteTreeExtensionTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'TrafficSplitSiteTreeExtensionTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class => [
            TrafficSplitSiteTreeExtension::class,
        ],
    ];

    public function testTrafficSplitRelationsAreNotEditableFromThePage(): void
    {
        $fields = $this->objFromFixture(SiteTree::class, 'entry-page')->getCMSFields();

        $this->assertNull($fields->fieldByName('Root.LaboratoryTrafficSplits'));
        $this->assertNull($fields->fieldByName('Root.LaboratoryContentVariations'));
        $this->assertNull($fields->dataFieldByName('LaboratoryTrafficSplits'));
        $this->assertNull($fields->dataFieldByName('LaboratoryContentVariations'));
    }

    /**
     * @param string $page
     * @param string|null $expectedRole
     * @return void
     */
    #[DataProvider('trafficSplitNoticeProvider')]
    public function testTrafficSplitNotice(string $page, ?string $expectedRole): void
    {
        $fields = $this->objFromFixture(SiteTree::class, $page)->getCMSFields();
        $notice = $fields->fieldByName('Root.Main.LaboratoryTrafficSplitNotice');

        if ($expectedRole === null) {
            $this->assertNull($notice, 'Pages outside any traffic split have no notice');

            return;
        }

        $this->assertNotNull($notice);
        $content = $notice->getContent();
        $this->assertStringContainsString('Page redesign test', $content);
        $this->assertStringContainsString('(not running)', $content);
        $this->assertStringContainsString($expectedRole, $content);
    }

    public static function trafficSplitNoticeProvider(): array
    {
        return [
            'entry page that is also a variation' => [
                'entry-page',
                'this page is the entry page and content variation &quot;A: original&quot;',
            ],
            'variation page only' => [
                'variation-page',
                'this page is the content variation &quot;B: redesign&quot;',
            ],
            'unrelated page' => [
                'unrelated-page',
                null,
            ],
        ];
    }

    public function testNoCanonicalTagWhileTrafficSplitIsNotRunning(): void
    {
        $page = $this->objFromFixture(SiteTree::class, 'variation-page');

        $this->assertArrayNotHasKey('canonical', $page->MetaComponents());
    }

    /**
     * @param string $page
     * @return void
     */
    #[DataProvider('canonicalPageProvider')]
    public function testRunningTrafficSplitPagesAreCanonicalToTheEntryPage(string $page): void
    {
        $this->publishTrafficSplit();
        $entryPage = $this->objFromFixture(SiteTree::class, 'entry-page');

        $tags = $this->objFromFixture(SiteTree::class, $page)->MetaComponents();

        $this->assertSame('canonical', $tags['canonical']['attributes']['rel']);
        $this->assertSame($entryPage->AbsoluteLink(), $tags['canonical']['attributes']['href']);
    }

    public static function canonicalPageProvider(): array
    {
        return [
            'entry page' => ['entry-page'],
            'variation page' => ['variation-page'],
        ];
    }

    public function testUnrelatedPageHasNoCanonicalTag(): void
    {
        $this->publishTrafficSplit();
        $page = $this->objFromFixture(SiteTree::class, 'unrelated-page');

        $this->assertArrayNotHasKey('canonical', $page->MetaComponents());
    }

    private function publishTrafficSplit(): void
    {
        foreach (['entry-page', 'variation-page'] as $page) {
            $this->objFromFixture(SiteTree::class, $page)->publishRecursive();
        }

        $this->objFromFixture(TrafficSplit::class, 'traffic-split')->publishRecursive();
    }
}
