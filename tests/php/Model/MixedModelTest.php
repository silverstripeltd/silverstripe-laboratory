<?php

namespace SilverStripe\Laboratory\Tests\Model;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\TrafficSplitElementalAreaExtension;
use SilverStripe\Laboratory\Extension\VariationIdentifierModelExtension;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\ArrayList;

class MixedModelTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'MixedModelTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        PageWithGenericModels::class,
        GenericContentModel::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        GenericContentModel::class => [
            VariationIdentifierModelExtension::class,
        ],
        PageWithGenericModels::class => [
            ElementalPageExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        TrafficSplitService::config()->set('element_variation_enabled', true);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetSecondaryVariationIdentifiersOnMixedModels(): void
    {
        /** @var PageWithGenericModels $page */
        $page = $this->objFromFixture(PageWithGenericModels::class, 'page1');

        $identifiers = TrafficSplitService::singleton()->getSecondaryVariationIdentifiers($page);
        $this->assertSame([
            'blue',
            'green',
            'orange',
            'red',
        ], $identifiers, 'We expect a specific set of secondary identifiers');
    }

    /**
     * @param string|null $identifier
     * @param array $expected
     * @return void
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @dataProvider trafficSplitElementCasesProvider
     */
    public function testTrafficSplitOnElements(?string $identifier, array $expected): void
    {
        /** @var PageWithGenericModels|ElementalPageExtension $page */
        $page = $this->objFromFixture(PageWithGenericModels::class, 'page1');

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

    public function trafficSplitElementCasesProvider(): array
    {
        return [
            'no variation identifier' => [
                null,
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (implicit)' => [
                'primary',
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'primary variation identifier (explicit)' => [
                'blue',
                [
                    'standard-block1',
                    'primary-block',
                    'standard-block2',
                    'standard-block3',
                ],
            ],
            'secondary variation identifier' => [
                'red',
                [
                    'standard-block1',
                    'standard-block2',
                    'secondary-block',
                    'standard-block3',
                ],
            ],
        ];
    }

    /**
     * @param string|null $identifier
     * @param array $expected
     * @return void
     * @throws Exception
     * @dataProvider trafficSplitModelCasesProvider
     */
    public function testTrafficSplitOnGenericModels(?string $identifier, array $expected): void
    {
        /** @var PageWithGenericModels $page */
        $page = $this->objFromFixture(PageWithGenericModels::class, 'page1');

        $expectedModels = array_map(function (string $modelIdentifier) {
            /** @var GenericContentModel $model */
            $model = $this->objFromFixture(GenericContentModel::class, $modelIdentifier);

            return $model->Title;
        }, $expected);

        if ($identifier) {
            TrafficSplitService::singleton()->setContentVariationIdentifier($identifier);
        }

        $list = TrafficSplitService::singleton()->getListWithTrafficSplit($page->ContentModels(), $page);

        $this->assertSame($expectedModels, $list->column('Title'), 'We expect a specific list of models');
    }

    public function trafficSplitModelCasesProvider(): array
    {
        return [
            'no variation identifier' => [
                null,
                [
                    'standard-model1',
                    'primary-model',
                    'standard-model2',
                    'standard-model3',
                ],
            ],
            'primary variation identifier (implicit)' => [
                'primary',
                [
                    'standard-model1',
                    'primary-model',
                    'standard-model2',
                    'standard-model3',
                ],
            ],
            'primary variation identifier (explicit)' => [
                'orange',
                [
                    'standard-model1',
                    'primary-model',
                    'standard-model2',
                    'standard-model3',
                ],
            ],
            'secondary variation identifier' => [
                'green',
                [
                    'standard-model1',
                    'standard-model2',
                    'secondary-model',
                    'standard-model3',
                ],
            ],
        ];
    }
}
