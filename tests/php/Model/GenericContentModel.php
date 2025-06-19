<?php

namespace SilverStripe\Laboratory\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property int $ParentID
 * @property int $Sort
 * @method PageWithGenericModels Parent()
 */
class GenericContentModel extends DataObject implements TestOnly
{
    private static string $table_name = 'GenericModelTest_GenericContentModel';

    private static string $singular_name = 'Generic content model';

    private static string $plural_name = 'Generic content models';

    private static string $class_description = 'Test model which can hold content but is not a content block';

    private static array $db = [
        'Title' => 'Varchar',
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Parent' => PageWithGenericModels::class,
    ];

    private static string $default_sort = 'Sort';
}
