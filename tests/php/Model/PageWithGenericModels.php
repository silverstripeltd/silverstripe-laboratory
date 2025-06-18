<?php

namespace SilverStripe\Laboratory\Tests\Model;

use Page;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\HasManyList;

/**
 * @method HasManyList|GenericContentModel[] ContentModels()
 */
class PageWithGenericModels extends Page implements TestOnly
{
    private static string $table_name = 'GenericModelTest_PageWithGenericModels';

    private static string $singular_name = 'Page with generic models';

    private static string $plural_name = 'Pages with generic models';

    private static string $class_description = 'Test page intended to be used for testing generic models';

    private static array $has_many = [
        'ContentModels' => GenericContentModel::class . '.Parent',
    ];

    /**
     * Extension point in @see TrafficSplitService::findValidIdentifiers()
     *
     * @param array $lists
     * @return void
     */
    public function collectLaboratoryContentLists(array &$lists): void
    {
        $lists[] = $this->ContentModels();
    }
}
