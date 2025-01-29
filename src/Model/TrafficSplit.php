<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\RequiredFields;
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
 * @property int $VariationChoiceExpiry
 * @method SiteTree EntryPage()
 * @method HasManyList|ContentVariation[] ContentVariations()
 * @mixin Versioned
 */
class TrafficSplit extends DataObject
{
    private const int DEFAULT_VARIATION_CHOICE_EXPIRY = 30;

    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'LaboratoryTrafficSplit';

    private static string $singular_name = 'Traffic split';

    private static string $plural_name = 'Traffic splits';

    private static array $db = [
        'Title' => 'Varchar',
        'VariationChoiceExpiry' => 'Int',
    ];

    private static array $has_one = [
        'EntryPage' => SiteTree::class,
    ];

    private static array $has_many = [
        'ContentVariations' => ContentVariation::class . '.Parent',
    ];

    private static array $owns = [
        'ContentVariations',
    ];

    private static array $defaults = [
        'Title' => 'Traffic split',
        'VariationChoiceExpiry' => self::DEFAULT_VARIATION_CHOICE_EXPIRY,
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([
                'Title',
                'VariationChoiceExpiry',
                'EntryPageID',
            ]);

            $fields->addFieldsToTab('Root.Main', [
                $titleField = TextField::create('Title', 'CMS Title'),
                $variationChoiceField = NumericField::create('VariationChoiceExpiry', 'Variation choice expiry (days)'),
                $entryPageField = TreeDropdownField::create('EntryPageID', 'Entry page', SiteTree::class),
            ]);

            $titleField->setDescription(
                'The CMS title will not be visible on your page, it is used to name your component '
                . 'so that it can be found easily from within the CMS'
            );

            $variationChoiceField
                ->setHTML5(true)
                ->setAttribute('min', 1)
                ->setAttribute('placeholder', self::DEFAULT_VARIATION_CHOICE_EXPIRY)
                ->setDescription(
                    'Defines the duration of the traffic split content variation choice for website visitor. '
                    . 'This setting should roughly match the duration of the experimentation period for a particular test.'
                )
            ;

            $entryPageField->setDescription(
                'The page where the experiment is expected to run on (entry point of website visitors)'
            );

            /** @var GridField $contentVariationField */
            $contentVariationField = $fields->fieldByName('Root.ContentVariations.ContentVariations');

            if ($contentVariationField) {
                $contentVariationConfig = $contentVariationField->getConfig();
                $contentVariationConfig->removeComponentsByType([
                    // Remove link action as this is a full ownership relation
                    GridFieldAddExistingAutocompleter::class,
                ]);

                /** @var GridFieldDeleteAction $deleteAction */
                $deleteAction = $contentVariationConfig->getComponentByType(GridFieldDeleteAction::class);

                // Replace unlink relation button with delete button as this is a full ownership relation
                $deleteAction?->setRemoveRelation(false);
            }
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
                    'VariationChoiceExpiry',
                    'EntryPageID',
                ]);
                $compositeValidator->addValidator($validator);
            }
        );

        return parent::getCMSCompositeValidator();
    }
}
