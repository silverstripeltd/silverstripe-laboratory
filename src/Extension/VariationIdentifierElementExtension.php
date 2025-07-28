<?php

namespace SilverStripe\Laboratory\Extension;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Laboratory\Service\TrafficSplitService;

/**
 * Add the capability to mark blocks with content variation identifiers
 *
 * @property string $LaboratoryVariationIdentifier
 * @method BaseElement|$this getOwner()
 */
class VariationIdentifierElementExtension extends Extension
{
    private static array $db = [
        'LaboratoryVariationIdentifier' => 'Text',
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        // Remove scaffolded field
        $fields->removeByName([
            'LaboratoryVariationIdentifier',
        ]);

        $service = TrafficSplitService::singleton();

        // Elemental content variation is disabled
        if (!$service->getElementVariationEnabled()) {
            return;
        }

        $primaryVariationName = $service->getElementPrimaryVariationName();
        $primaryVariationMessage = sprintf(
            'To use a block as a primary variation block, '
            . 'please ensure to use "%s" as the variation identifier.',
            $primaryVariationName
        );

        $fields->addFieldsToTab(
            'Root.Settings',
            [
                CompositeField::create([
                    HeaderField::create('LaboratoryVariationHeading', 'Laboratory settings'),
                    $variationIdentifierField = TextareaField::create(
                        'LaboratoryVariationIdentifier',
                        'Variation identifier'
                    ),
                ]),
            ],
        );

        $variationIdentifierField->setDescription(
            'Values used for displaying content variations in the front end (newline separated list). '
            . 'Only allow use of alphabet (lowercase), numbers, hyphen and underscore characters, '
            . 'when left blank content variation feature is considered not in use for this block. '
            . $primaryVariationMessage
        );
    }

    protected function onBeforeWrite(): void
    {
        $owner = $this->getOwner();

        $owner->LaboratoryVariationIdentifier = TrafficSplitService::singleton()->sanitiseVariationIdentifier(
            $owner->LaboratoryVariationIdentifier
        );
    }
}
