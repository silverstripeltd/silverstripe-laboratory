<?php

namespace SilverStripe\Laboratory\Tests\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\TrafficSplitSiteTreeExtension;
use SilverStripe\Laboratory\Model\ContentVariation;
use SilverStripe\Laboratory\Model\TrafficSplit;

class TrafficSplitTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'TrafficSplitTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class => [
            TrafficSplitSiteTreeExtension::class,
        ],
    ];

    public function testTrafficSplitHasContentVariation(): void
    {
        /** @var TrafficSplit $trafficSplit */
        $trafficSplit = $this->objFromFixture(TrafficSplit::class, 'traffic-split1');

        /** @var ContentVariation $contentVariation1 */
        $contentVariation1 = $this->objFromFixture(ContentVariation::class, 'content-variation1');

        /** @var ContentVariation $contentVariation2 */
        $contentVariation2 = $this->objFromFixture(ContentVariation::class, 'content-variation2');

        $this->assertEqualsCanonicalizing(
            [
                $contentVariation1->Title,
                $contentVariation2->Title,
            ],
            $trafficSplit->ContentVariations()->column('Title'),
            'Traffic split has content variations'
        );

        $this->assertEquals(
            $trafficSplit->Title,
            $contentVariation1->Parent()->Title,
            'Content variation 1 has traffic split'
        );
        $this->assertEquals(
            $trafficSplit->Title,
            $contentVariation2->Parent()->Title,
            'Content variation 2 has traffic split'
        );
    }

    public function testPageRelations(): void
    {
        /** @var SiteTree|TrafficSplitSiteTreeExtension $entryPage */
        $entryPage = $this->objFromFixture(SiteTree::class, 'entry-page1');

        /** @var SiteTree|TrafficSplitSiteTreeExtension $variationPage */
        $variationPage = $this->objFromFixture(SiteTree::class, 'variation-page1');

        /** @var TrafficSplit $trafficSplit */
        $trafficSplit = $this->objFromFixture(TrafficSplit::class, 'traffic-split1');

        /** @var ContentVariation $contentVariation */
        $contentVariation = $this->objFromFixture(ContentVariation::class, 'content-variation1');

        $this->assertEquals(
            $entryPage->Title,
            $trafficSplit->EntryPage()->Title,
            'Traffic split has entry page'
        );

        $this->assertEquals(
            [
                $trafficSplit->Title,
            ],
            $entryPage->LaboratoryTrafficSplits()->column('Title'),
            'Entry page has traffic splits'
        );

        $this->assertEquals(
            $variationPage->Title,
            $contentVariation->VariationPage()->Title,
            'Content variation has variation page'
        );

        $this->assertEquals(
            [
                $contentVariation->Title,
            ],
            $variationPage->LaboratoryContentVariations()->column('Title'),
            'Variation page has content variations'
        );
    }
}
