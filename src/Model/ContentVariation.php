<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Represents content a single content variation for a specific A/B test setup
 *
 * @property string $Title
 * @property int $Probability
 * @property string $VariationIdentifier
 * @property int $ParentID
 * @property int $VariationPageID
 * @method TrafficSplit Parent()
 * @method SiteTree VariationPage()
 * @mixin Versioned
 */
class ContentVariation extends DataObject
{
    protected const int MINIMUM_TRAFFIC_RATIO = 1;
    protected const int MAXIMUM_TRAFFIC_RATIO = 99;
    protected const int DEFAULT_TRAFFIC_RATIO = 50;

    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'LaboratoryContentVariation';

    private static string $singular_name = 'Content variation';

    private static string $plural_name = 'Content variations';

    private static array $db = [
        'Title' => 'Varchar',
        'Probability' => 'Int',
        'VariationIdentifier' => 'Varchar',
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
        'Probability' => ContentVariation::DEFAULT_TRAFFIC_RATIO,
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'Probability' => 'Probability',
        'VariationPage.Title' => 'Variation page title',
        'VariationIdentifier' => 'Variation identifier',
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([
                'Title',
                'Probability',
                'VariationIdentifier',
                'VariationPageID',
                'ParentID',
            ]);

            $service = TrafficSplitService::singleton();
            $variationPage = $this->VariationPage();
            $variationIdentifiersValues = [];

            // Attempt to find content variation identifiers for the variation page
            if ($variationPage->exists()) {
                $variationIdentifiers = $service->getSecondaryVariationIdentifiers($variationPage);

                if (count($variationIdentifiers) > 0) {
                    $variationIdentifiersValues = array_combine($variationIdentifiers, $variationIdentifiers);
                }
            }

            $fields->addFieldsToTab('Root.Main', [
                $titleField = TextField::create('Title', 'CMS Title'),
                $probabilityField = NumericField::create('Probability', 'Probability'),
                $variationPageField = TreeDropdownField::create('VariationPageID', 'Variation page', SiteTree::class),
                $variationIdentifierField = DropdownField::create(
                    'VariationIdentifier',
                    'Variation identifier',
                    $variationIdentifiersValues
                ),
            ]);

            $titleField->setDescription(
                'The CMS title will not be visible on your page, it is used to name your component '
                . 'so that it can be found easily from within the CMS.'
            );

            $trafficSplit = $this->Parent();
            $totalProbability = $trafficSplit->exists()
                ? (int) $trafficSplit
                    ->ContentVariations()
                    ->sum('Probability')
                : 0;

            $message = sprintf(
                'Probability of this content variation being selected for website visitors in percentage '
                . 'points (%d to %d). The sum of all probabilities in this test is currently %d out of %d.',
                ContentVariation::MINIMUM_TRAFFIC_RATIO,
                ContentVariation::MAXIMUM_TRAFFIC_RATIO,
                $totalProbability,
                TrafficSplit::TOTAL_PROBABILITY,
            );
            $probabilityField
                ->setHTML5(true)
                ->setAttribute('min', ContentVariation::MINIMUM_TRAFFIC_RATIO)
                ->setAttribute('max', ContentVariation::MAXIMUM_TRAFFIC_RATIO)
                ->setAttribute('placeholder', ContentVariation::DEFAULT_TRAFFIC_RATIO)
                ->setDescription($message);

            $variationPageField->setDescription(
                'The page where the experiment is expected to redirect website visitors to for this content variation.'
            );

            $variationDescription = count($variationIdentifiersValues) > 0
                ? 'Choose a specific content variation of the variation page. '
                . 'Primary variation does not need an identifier.'
                : 'Your variation page has no variation identifiers set up.';
            $variationIdentifierField
                ->setEmptyString('-- select --')
                ->setDescription($variationDescription);

            if ($service->getElementVariationEnabled()) {
                return;
            }

            // Content variation within page feature is not enabled,
            // change the field to read only as we don't need to interact with it
            $variationIdentifierField->setRightTitle('Feature disabled');
            $readOnlyVariationField = $variationIdentifierField->performReadonlyTransformation();
            $fields->replaceField($variationIdentifierField->getName(), $readOnlyVariationField);
        });

        return parent::getCMSFields();
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $this->beforeExtending(
            'updateCMSCompositeValidator',
            function (CompositeValidator $compositeValidator): void {
                $validator = RequiredFieldsValidator::create([
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
