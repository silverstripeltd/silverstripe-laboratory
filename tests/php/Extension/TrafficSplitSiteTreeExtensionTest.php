<?php

namespace SilverStripe\Laboratory\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\TrafficSplitSiteTreeExtension;

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
}
