<?php

namespace SilverStripe\Laboratory\Tests\Model;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Extension\VariationIdentifierModelExtension;
use SilverStripe\Laboratory\Service\TrafficSplitService;

class GenericModelTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'GenericModelTest.yml';

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
    ];

    /**
     * Just a test 123
     *
     * @return void
     * @throws Exception
     */
    public function testGetSecondaryVariationIdentifiersOnGenericModels(): void
    {
        /** @var PageWithGenericModels $page */
        $page = $this->objFromFixture(PageWithGenericModels::class, 'page1');

        $identifiers = TrafficSplitService::singleton()->getSecondaryVariationIdentifiers($page);
        $this->assertSame([
            'blue',
            'red',
        ], $identifiers, 'We expect a specific set of secondary identifiers');
    }

    /**
     * @param string|null $identifier
     * @param array $expected
     * @return void
     * @throws Exception
     */
    #[DataProvider('trafficSplitModelCasesProvider')]
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

    public static function trafficSplitModelCasesProvider(): array
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
                'blue',
                [
                    'standard-model1',
                    'primary-model',
                    'standard-model2',
                    'standard-model3',
                ],
            ],
            'secondary variation identifier' => [
                'red',
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
