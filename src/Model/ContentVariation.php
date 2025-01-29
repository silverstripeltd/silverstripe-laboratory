<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;

/**
 * Represents content a single content variation for a specific A/B test setup
 *
 * @property string $Title
 * @property float $TrafficRatio
 * @property int $ParentID
 * @property int $VariationPageID
 * @method TrafficSplit Parent()
 * @method SiteTree VariationPage()
 */
class ContentVariation extends DataObject
{
    private static string $table_name = 'LaboratoryContentVariation';

    private static string $singular_name = 'Content variation';

    private static string $plural_name = 'Content variations';

    private static array $db = [
        'Title' => 'Varchar',
        'TrafficRatio' => 'Percentage(2)',
    ];

    private static array $has_one = [
        'Parent' => TrafficSplit::class,
        'VariationPage' => SiteTree::class,
    ];
}
