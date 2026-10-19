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
 * @extends Extension<BaseElement>
 * @extends Extension<VariationIdentifierElementExtension>
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

        $variationIdentifierField->setDescription($service->getVariationIdentifierFieldDescription());
    }

    protected function onBeforeWrite(): void
    {
        $owner = $this->getOwner();

        $owner->LaboratoryVariationIdentifier = TrafficSplitService::singleton()->sanitiseVariationIdentifier(
            $owner->LaboratoryVariationIdentifier
        );
    }
}
