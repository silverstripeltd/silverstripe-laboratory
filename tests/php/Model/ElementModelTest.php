<?php

namespace SilverStripe\Laboratory\Tests\Model;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Exception;
use Page;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\TrafficSplitElementalAreaExtension;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\Model\List\ArrayList;

class ElementModelTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ElementModelTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        Page::class => [
            ElementalPageExtension::class,
        ],
    ];

    /**
     * @param bool $enabled
     * @param array $expected
     * @return void
     * @throws Exception
     * @dataProvider secondaryIdentifiersElementsCasesProvider
     */
    public function testGetSecondaryVariationIdentifiersOnElements(bool $enabled, array $expected): void
    {
        TrafficSplitService::config()->set('element_variation_enabled', $enabled);

        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'page1');

        $identifiers = TrafficSplitService::singleton()->getSecondaryVariationIdentifiers($page);
        $this->assertSame($expected, $identifiers, 'We expect a specific set of secondary identifiers');
    }

    public static function secondaryIdentifiersElementsCasesProvider(): array
    {
        return [
            'enabled' => [
                true,
                [
                    'blue',
                    'red',
                ],
            ],
            'disabled' => [
                false,
                [],
            ],
        ];
    }

    /**
     * @param string|null $identifier
     * @param bool $enabled
     * @param array $expected
     * @return void
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @dataProvider trafficSplitElementCasesProvider
     */
    public function testTrafficSplitOnElements(?string $identifier, bool $enabled, array $expected): void
    {
        TrafficSplitService::config()->set('element_variation_enabled', $enabled);

        /** @var Page|ElementalPageExtension $page */
        $page = $this->objFromFixture(Page::class, 'page1');

        /** @var ElementalArea|TrafficSplitElementalAreaExtension $area */
        $area = $page->ElementalArea();

        $expectedBlocks = array_map(function (string $blockIdentifier) {
            /** @var ElementContent $block */
            $block = $this->objFromFixture(ElementContent::class, $blockIdentifier);

            return $block->Title;
        }, $expected);

        if ($identifier) {
            TrafficSplitService::singleton()->setContentVariationIdentifier($identifier);
        }

        $controllers = $area->ElementControllersWithTrafficSplit();
        $list = ArrayList::create()->setDataClass(BaseElement::class);

        /** @var ElementController $controller */
        foreach ($controllers as $controller) {
            $list->add($controller->getElement());
        }

        $this->assertSame($expectedBlocks, $list->column('Title'), 'We expect a specific list of blocks');
    }

    public static function trafficSplitElementCasesProvider(): array
    {
        return [
            'no variation identifier (enabled)' => [
                null,
                true,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (enabled, implicit)' => [
                'primary',
                true,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (enabled, explicit)' => [
                'blue',
                true,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'secondary variation identifier (enabled)' => [
                'red',
                true,
                [
                    'standard-block1',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
            'no variation identifier (disabled)' => [
                null,
                false,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (disabled, implicit)' => [
                'primary',
                false,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (disabled, explicit)' => [
                'blue',
                false,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
            'secondary variation identifier (disabled)' => [
                'red',
                false,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
        ];
    }
}
