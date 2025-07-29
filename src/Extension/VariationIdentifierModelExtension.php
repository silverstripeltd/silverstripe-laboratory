<?php

namespace SilverStripe\Laboratory\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\DataObject;

/**
 * Add the capability to mark generic model with content variation identifiers
 * TODO refactor the code to be more DRY in relation with @see VariationIdentifierElementExtension
 *
 * @property string $LaboratoryVariationIdentifier
 * @extends Extension<DataObject>
 * @extends Extension<VariationIdentifierModelExtension>
 */
class VariationIdentifierModelExtension extends Extension
{
    private static array $db = [
        'LaboratoryVariationIdentifier' => 'Text',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        // Remove scaffolded field
        $fields->removeByName([
            'LaboratoryVariationIdentifier',
        ]);

        $service = TrafficSplitService::singleton();

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

    public function onBeforeWrite(): void
    {
        $owner = $this->getOwner();

        $owner->LaboratoryVariationIdentifier = TrafficSplitService::singleton()->sanitiseVariationIdentifier(
            $owner->LaboratoryVariationIdentifier
        );
    }
}
