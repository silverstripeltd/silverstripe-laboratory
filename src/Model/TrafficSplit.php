<?php

namespace SilverStripe\Laboratory\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
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
 * @method HasManyList<ContentVariation> ContentVariations()
 * @mixin Versioned
 */
class TrafficSplit extends DataObject
{
    protected const int MINIMUM_VARIATION_CHOICE_EXPIRY = 1;
    protected const int DEFAULT_VARIATION_CHOICE_EXPIRY = 30;
    protected const int MINIMUM_CONTENT_VARIATIONS = 2;
    public const int TOTAL_PROBABILITY = 100;

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

    private static array $cascade_duplicates = [
        'ContentVariations',
    ];

    private static array $cascade_deletes = [
        'ContentVariations',
    ];

    private static array $defaults = [
        'Title' => 'Traffic split',
        'VariationChoiceExpiry' => TrafficSplit::DEFAULT_VARIATION_CHOICE_EXPIRY,
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'EntryPage.Title' => 'Entry page title',
        'ContentVariations.Count' => 'Content variations',
        'getStatusSummary' => 'Status',
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

            $statusMessage = $this->getStatusMessage();

            if ($statusMessage) {
                $fields->addFieldsToTab('Root.Main', [
                    LiteralField::create('StatusMessage', $statusMessage),
                ]);
            }

            $titleField->setDescription(
                'The CMS title will not be visible on your page, it is used to name your component '
                . 'so that it can be found easily from within the CMS'
            );

            $variationChoiceField
                ->setHTML5(true)
                ->setAttribute('min', TrafficSplit::MINIMUM_VARIATION_CHOICE_EXPIRY)
                ->setAttribute('placeholder', TrafficSplit::DEFAULT_VARIATION_CHOICE_EXPIRY)
                ->setDescription(
                    'Defines the duration of the traffic split content variation choice '
                    . 'for website visitor. This setting should roughly match the duration '
                    . 'of the experimentation period for a particular test.'
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
                $validator = RequiredFieldsValidator::create([
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
     * Format the datetime of the end of the traffic split active period
     *
     * @return DBDatetime|null
     */
    public function getActivationEndDate(): ?DBDatetime
    {
        return Versioned::withVersionedMode(function (): ?DBDatetime {
            Versioned::set_stage(Versioned::LIVE);

            /** @var TrafficSplit $trafficSplit */
            $trafficSplit = TrafficSplit::get()->byID($this->ID);

            if (!$trafficSplit) {
                return null;
            }

            $publishedAt = $trafficSplit->LastEdited;
            $publishDate = DBDatetime::create_field('Datetime', $publishedAt);
            $publishDate->modify(sprintf('+%d day', $this->VariationChoiceExpiry));

            return $publishDate;
        });
    }

    /**
     * Determine if any status needs to be communicated via a message
     *
     * @return string|null
     */
    private function getStatusMessage(): ?string
    {
        // We don't have any data to validate yet
        if (!$this->isInDB()) {
            return null;
        }

        $entryPage = $this->EntryPage();
        $isTrafficSplitPublished = $this->isPublished();

        if ($isTrafficSplitPublished && $entryPage->exists()) {
            return sprintf(
                '<p class="message good">Traffic split is active at the "%s" page (%s) '
                . 'and the activation ends on %s</p>',
                Convert::raw2xml($entryPage->Title),
                $this->getEntryPageLinks($entryPage),
                $this->getActivationEndDate()->Nice()
            );
        }

        $isEntryPageUsed = $this->isEntryPagesUsed();

        if ($isEntryPageUsed) {
            return '<p class="message error">Other traffic split is already active on the current entry page</p>';
        }

        if (!$entryPage->isPublished()) {
            return '<p class="message warning">Entry page is not published</p>';
        }

        $contentVariations = $this->ContentVariations();

        if ($contentVariations->count() < TrafficSplit::MINIMUM_CONTENT_VARIATIONS) {
            return '<p class="message warning">Traffic split needs to have at least two content variations</p>';
        }

        if (!$this->hasValidProbability()) {
            $totalProbability = $this->getTotalProbability();

            return sprintf(
                '<p class="message warning">Traffic split needs to have a balanced probability distribution '
                . '(sum of probabilities across all content variations has to be %d, current value is %d)</p>',
                TrafficSplit::TOTAL_PROBABILITY,
                $totalProbability,
            );
        }

        if (!$this->areAllContentVariationsPagesPublished()) {
            return '<p class="message warning">All content variation pages need to be published</p>';
        }

        if (!$isTrafficSplitPublished && $this->isReady()) {
            return '<p class="message good">Traffic split is ready to be activated</p>';
        }

        return null;
    }

    /**
     * Links to the entry page on the website and to its edit form in the CMS
     *
     * @param SiteTree $entryPage
     * @return string
     */
    private function getEntryPageLinks(SiteTree $entryPage): string
    {
        $links = [
            sprintf(
                '<a href="%s" target="_blank" rel="noopener">public page</a>',
                Convert::raw2att($entryPage->AbsoluteLink())
            ),
        ];
        $editLink = $entryPage->getCMSEditLink();

        if ($editLink) {
            $links[] = sprintf('<a href="%s">edit in CMS</a>', Convert::raw2att($editLink));
        }

        return implode(' | ', $links);
    }

    /**
     * Intended for CMS UI (summary fields) use
     *
     * @return LiteralField|null
     */
    public function getStatusSummary(): ?LiteralField
    {
        $statusMessage = $this->getStatusMessage();

        if (!$statusMessage) {
            return null;
        }

        return LiteralField::create('StatusSummary', $statusMessage);
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

        $trafficSplits = TrafficSplit::get()
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
        if ($contentVariations->count() < TrafficSplit::MINIMUM_CONTENT_VARIATIONS) {
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
        if (!$this->areAllContentVariationsPagesPublished()) {
            return false;
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
        $totalProbability = $this->getTotalProbability();

        return $totalProbability === TrafficSplit::TOTAL_PROBABILITY;
    }

    private function getTotalProbability(): int
    {
        return (int) $this
            ->ContentVariations()
            ->sum('Probability');
    }

    private function areAllContentVariationsPagesPublished(): bool
    {
        $contentVariations = $this->ContentVariations();

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
}
