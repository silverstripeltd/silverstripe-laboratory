<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\Versioned;

/**
 * Represents a traffic split part of the A/B test setup
 * This is where pages associated with the experimental are defined
 * Entry page is the page where the experiment is running on
 * Content variations define a set of different user experiences which are in scope of the experiment
 *
 * @property string $Title
 * @property int $EntryPage
 * @property int $CookieExpiry
 * @method SiteTree EntryPage()
 * @method HasManyList|ContentVariation[] ContentVariations()
 * @mixin Versioned
 */
class TrafficSplit extends DataObject
{
    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'LaboratoryTrafficSplit';

    private static string $singular_name = 'Traffic split';

    private static string $plural_name = 'Traffic splits';

    private static array $db = [
        'Title' => 'Varchar',
        'CookieExpiry' => 'Int',
    ];

    private static array $has_one = [
        'EntryPage' => SiteTree::class,
    ];

    private static array $has_many = [
        'ContentVariations' => ContentVariation::class . '.Parent',
    ];

    private static array $defaults = [
        'Title' => 'Traffic split',
        'CookieExpireDays' => 30,
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'Title',
            'CookieExpiry',
            'EntryPageID',
        ]);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title'),
            $cookieExpiryField = NumericField::create('CookieExpiry', 'Cookie expiry (days)'),
            $entryPageField = TreeDropdownField::create('EntryPageID', 'Entry page', SiteTree::class),
        ]);

        $cookieExpiryField->setDescription(
            'Defines the duration of the traffic split content variation choice for website visitor. '
            . 'This setting should roughly match the duration of the experimentation period for a particular test.'
        );

        return $fields;
    }
}
