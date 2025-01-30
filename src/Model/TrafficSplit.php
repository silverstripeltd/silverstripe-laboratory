<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
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

            $statusField = $this->getStatusField();

            if ($statusField) {
                $fields->addFieldsToTab('Root.Main', [
                    $statusField,
                ]);
            }

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

    public function canPublish($member = null): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        if ($this->isEntryPagesUsed()) {
            return false;
        }

        return $this->canEdit();
    }

    /**
     * Determine if any status needs to be communicated via a message
     *
     * @return LiteralField|null
     */
    private function getStatusField(): ?LiteralField
    {
        $entryPage = $this->EntryPage();

        if ($this->isPublished() && $entryPage->exists()) {
            $runningMessage = sprintf(
                '<p class="message good">Traffic split is active at <a href="%s" target="_blank">%s</a> page</p>',
                $entryPage->AbsoluteLink(),
                $entryPage->Title
            );

            return LiteralField::create('StatusMessage', $runningMessage);
        }

        return null;
    }

    /**
     * Check to see if other tests are running on the same canonical page as this test is
     *
     * @return bool
     */
    private function isEntryPagesUsed(): bool
    {
        $entryPage = $this->EntryPage();

        // Not used because we don't have an entry page to check yet
        if (!$entryPage->exists()) {
            return false;
        }

        $trafficSplits = self::get()
            ->filter([
                // Looking for specific entry page to validate
                'EntryPageID' => $entryPage->ID,
                // Exclude the current traffic split as we are interested in usage outside of this split
                'ID:Not' => $this->ID,
            ]);

        /** @var TrafficSplit $trafficSplit */
        foreach ($trafficSplits as $trafficSplit) {
            // Traffic split is active so the entry page is already in use
            if ($trafficSplit->isPublished()) {
                return true;
            }
        }

        // We haven't found any other test that uses the entry page
        return false;
    }

    /**
     * Is this traffic split ready to be activated?
     *
     * @return bool
     */
    private function isReady(): bool
    {
        $entryPage = $this->EntryPage();
        $contentVariations = $this->ContentVariations();

        // Entry page missing
        if (!$entryPage->exists()) {
            return false;
        }

        // Not enough content variations (need at least two for a traffic split)
        if ($contentVariations->count() < 2 ) {
            return false;
        }

        // Invalid total probability
        if (!$this->hasValidProbability()) {
            return false;
        }

        // Check that the entry page is published
        if (!$entryPage->isPublished()) {
            return false;
        }

        // Check that all content variations are published
        foreach ($contentVariations as $contentVariation) {
            $variationPage = $contentVariation->VariationPage();

            // Variation page missing
            if (!$variationPage->exists()) {
                return false;
            }

            // Variation page not published
            if (!$variationPage->isPublished()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the probability of all content variation rules have a valid setup
     *
     * @return bool
     */
    private function hasValidProbability(): bool
    {
        $totalProbability = (int) $this
            ->ContentVariations()
            ->sum('Probability');

        return $totalProbability === 100;
    }
}
