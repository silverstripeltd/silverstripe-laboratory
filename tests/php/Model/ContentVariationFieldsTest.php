<?php

namespace SilverStripe\Laboratory\Tests\Model;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use Page;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Laboratory\Model\ContentVariation;
use SilverStripe\Laboratory\Service\TrafficSplitService;

class ContentVariationFieldsTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ContentVariationFieldsTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        Page::class => [
            ElementalPageExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        TrafficSplitService::config()->set('element_variation_enabled', true);
    }

    /**
     * @param string $variation
     * @param string $expectedMessage
     * @return void
     */
    #[DataProvider('missingIdentifiersProvider')]
    public function testMissingIdentifiersAreExplained(string $variation, string $expectedMessage): void
    {
        $fields = $this->objFromFixture(ContentVariation::class, $variation)->getCMSFields();

        $this->assertNull($fields->dataFieldByName('VariationIdentifier'), 'No empty dropdown is shown');
        $info = $fields->fieldByName('Root.Main.VariationIdentifierInfo');
        $this->assertInstanceOf(ReadonlyField::class, $info);
        $this->assertStringContainsString($expectedMessage, $info->getDescription());
    }

    public static function missingIdentifiersProvider(): array
    {
        return [
            'variation page not chosen yet' => [
                'no-page',
                'Choose a variation page and save',
            ],
            'variation page without secondary identifiers' => [
                'plain-page-variation',
                'has no blocks marked with a secondary identifier',
            ],
        ];
    }

    public function testSecondaryIdentifiersOfTheVariationPageCanBeChosen(): void
    {
        $fields = $this->objFromFixture(ContentVariation::class, 'block-page-variation')->getCMSFields();

        /** @var DropdownField $field */
        $field = $fields->dataFieldByName('VariationIdentifier');
        $this->assertInstanceOf(DropdownField::class, $field);
        $this->assertSame(['secondary' => 'secondary'], $field->getSource());
        $this->assertSame('None (primary content)', $field->getEmptyString());
    }

    public function testSavedIdentifierMissingFromThePageStaysSelectable(): void
    {
        $fields = $this->objFromFixture(ContentVariation::class, 'removed-identifier-variation')->getCMSFields();

        /** @var DropdownField $field */
        $field = $fields->dataFieldByName('VariationIdentifier');
        $this->assertInstanceOf(DropdownField::class, $field);
        $this->assertSame(['retired' => 'retired (not found on the variation page)'], $field->getSource());
    }

    public function testIdentifierIsReadOnlyWhenBlockVariationsAreDisabled(): void
    {
        TrafficSplitService::config()->set('element_variation_enabled', false);

        $fields = $this->objFromFixture(ContentVariation::class, 'block-page-variation')->getCMSFields();

        $this->assertTrue($fields->dataFieldByName('VariationIdentifier')->isReadonly());
    }
}
