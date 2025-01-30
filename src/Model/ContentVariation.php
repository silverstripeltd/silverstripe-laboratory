<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Represents content a single content variation for a specific A/B test setup
 *
 * @property string $Title
 * @property int $Probability
 * @property int $ParentID
 * @property int $VariationPageID
 * @method TrafficSplit Parent()
 * @method SiteTree VariationPage()
 * @mixin Versioned
 */
class ContentVariation extends DataObject
{
    private const int DEFAULT_TRAFFIC_RATIO = 50;

    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'LaboratoryContentVariation';

    private static string $singular_name = 'Content variation';

    private static string $plural_name = 'Content variations';

    private static array $db = [
        'Title' => 'Varchar',
        'Probability' => 'Int',
    ];

    private static array $has_one = [
        'Parent' => TrafficSplit::class,
        'VariationPage' => SiteTree::class,
    ];

    private static array $owned_by = [
        'Parent',
    ];

    private static array $defaults = [
        'Title' => 'Content variation',
        'Probability' => self::DEFAULT_TRAFFIC_RATIO,
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([
                'Title',
                'Probability',
                'VariationPageID',
                'ParentID',
            ]);

            $fields->addFieldsToTab('Root.Main', [
                $titleField = TextField::create('Title', 'CMS Title'),
                $probabilityField = NumericField::create('Probability', 'Probability'),
                $variationPageField = TreeDropdownField::create('VariationPageID', 'Variation page', SiteTree::class),
            ]);

            $titleField->setDescription(
                'The CMS title will not be visible on your page, it is used to name your component '
                . 'so that it can be found easily from within the CMS'
            );

            $trafficSplit = $this->Parent();
            $totalProbability = $trafficSplit->exists()
                ? (int) $trafficSplit
                    ->ContentVariations()
                    ->sum('Probability')
                : 0;

            $message = sprintf('Probability of this content variation being selected for website visitors is percentage points (1 to 99). '
                . 'The sum of all probabilities in this test is currently %d out of 100.', $totalProbability);
            $probabilityField
                ->setHTML5(true)
                ->setAttribute('min', 1)
                ->setAttribute('max', 99)
                ->setAttribute('placeholder', self::DEFAULT_TRAFFIC_RATIO)
                ->setDescription($message)
            ;

            $variationPageField->setDescription(
                'The page where the experiment is expected to redirect website visitors to for this content variation'
            );
        });

        return parent::getCMSFields();
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $this->beforeExtending(
            'updateCMSCompositeValidator',
            function (CompositeValidator $compositeValidator): void {
                $validator = RequiredFields::create([
                    'Title',
                    'Probability',
                    'VariationPageID',
                ]);
                $compositeValidator->addValidator($validator);
            }
        );

        return parent::getCMSCompositeValidator();
    }
}
