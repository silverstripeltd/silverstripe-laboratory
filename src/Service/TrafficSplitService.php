<?php

namespace SilverStripe\Laboratory\Service;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Resettable;
use SilverStripe\Laboratory\Extension\VariationIdentifierElementExtension;
use SilverStripe\Laboratory\Extension\VariationIdentifierModelExtension;
use SilverStripe\Laboratory\Tests\Model\GenericContentModel;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * Traffic split content variation handling
 */
class TrafficSplitService implements Resettable
{

    use Configurable;
    use Injectable;

    /**
     * @var string
     * @config
     */
    private static string $traffic_split_marker = 'laboratory-traffic-split';

    /**
     * @var string
     * @config
     */
    private static string $traffic_split_cookie_name = 'laboratory-traffic-split';

    /**
     * @var bool
     * @config
     */
    private static bool $element_variation_enabled = false;

    /**
     * @var string
     * @config
     */
    private static string $element_primary_variation_name = 'primary';

    protected string $contentVariationIdentifier = '';

    /**
     * In-memory cache for valid identifiers
     *
     * @var array
     */
    protected array $validIdentifiers = [];

    public static function reset(): void
    {
        TrafficSplitService::singleton()->clearData();
    }

    public function getTrafficSplitMarker(): string
    {
        return (string) $this->config()->get('traffic_split_marker');
    }

    public function getTrafficSplitCookieName(): string
    {
        return (string) $this->config()->get('traffic_split_cookie_name');
    }

    public function getElementVariationEnabled(): bool
    {
        return (bool) $this->config()->get('element_variation_enabled');
    }

    public function getElementPrimaryVariationName(): string
    {
        return (string) $this->config()->get('element_primary_variation_name');
    }

    /**
     * CMS help text for the variation identifier field on blocks and other content models
     *
     * @return string
     */
    public function getVariationIdentifierFieldDescription(): string
    {
        return sprintf(
            'Leave blank to show this to every visitor. '
            . 'Enter "%1$s" to show it only in the primary (original) version of the page, which is also '
            . 'what visitors without a variation and search engines see. '
            . 'Enter any other word, such as "secondary", to show it only to visitors of that variation: '
            . 'once published it no longer appears on the normal page. '
            . 'Marking a block does not start a test; set up a traffic split in the Laboratory section. '
            . 'One identifier per line, using lowercase letters, numbers, hyphens and underscores.',
            $this->getElementPrimaryVariationName()
        );
    }

    public function getContentVariationIdentifier(): string
    {
        return $this->contentVariationIdentifier;
    }

    public function setContentVariationIdentifier(string $contentVariationIdentifier): TrafficSplitService
    {
        $this->contentVariationIdentifier = $contentVariationIdentifier;

        return $this;
    }

    public function clearData(): TrafficSplitService
    {
        $this->contentVariationIdentifier = '';
        $this->validIdentifiers = [];

        return $this;
    }

    /**
     * @param SS_List $controllers
     * @param SiteTree $page
     * @return SS_List
     * @throws Exception
     */
    public function getElementControllersWithTrafficSplit(SS_List $controllers, SiteTree $page): SS_List
    {
        // Default behaviour: If there's no content variation query param included,
        // we only need to show the ones that have no content variation identifier or are the primary variant
        $identifier = $this->getContentVariationIdentifier();
        $defaultIdentifier = TrafficSplitService::singleton()->getElementPrimaryVariationName();

        // We don't have an identifier set so we will fall back on the default
        if (!$identifier) {
            return $this->filterControllers($controllers, $defaultIdentifier);
        }

        // Get all valid variant identifiers, so we can validate the identifier passed via global state
        $identifiers = $this->getValidIdentifiers($page);

        return in_array($identifier, $identifiers)
            // Identifier is valid - use the identifier
            ? $this->filterControllers($controllers, $identifier)
            // Identifier is invalid - fall back to default
            : $this->filterControllers($controllers, $defaultIdentifier);
    }

    /**
     * @param SS_List $list
     * @param SiteTree $page
     * @return SS_List
     * @throws Exception
     */
    public function getListWithTrafficSplit(SS_List $list, SiteTree $page): SS_List
    {
        // Default behaviour: If there's no content variation query param included,
        // we only need to show the ones that have no content variation identifier or are the primary variant
        $identifier = $this->getContentVariationIdentifier();
        $defaultIdentifier = TrafficSplitService::singleton()->getElementPrimaryVariationName();

        // We don't have an identifier set so we will fall back on the default
        if (!$identifier) {
            return $this->filterList($list, $defaultIdentifier);
        }

        // Get all valid variant identifiers, so we can validate the identifier passed via global state
        $identifiers = $this->getValidIdentifiers($page);

        return in_array($identifier, $identifiers)
            // Identifier is valid - use the identifier
            ? $this->filterList($list, $identifier)
            // Identifier is invalid - fall back to default
            : $this->filterList($list, $defaultIdentifier);
    }

    /**
     * Apply sanitization and data consistency to identifier data
     *
     * @param string|null $identifier
     * @return string|null
     */
    public function sanitiseVariationIdentifier(?string $identifier): ?string
    {
        if (!$identifier) {
            // Value is empty, so we can just bail out as there is nothing to process
            return $identifier;
        }

        // Unpack, sanitise and pack data
        $identifierData = $this->unSerialiseIdentifierData($identifier);
        $identifierData = array_map(function (string $identifier): string {
            $identifier = trim($identifier);

            return $this->sanitiseIdentifier($identifier);
        }, $identifierData);
        $identifierData = $this->formatIdentifiers($identifierData);

        return $this->serialiseIdentifierData($identifierData);
    }

    /**
     * Get all secondary variation identifiers (distinct list)
     *
     * @param SiteTree $page
     * @return array
     * @throws Exception
     */
    public function getSecondaryVariationIdentifiers(SiteTree $page): array
    {
        $identifiers = $this->getValidIdentifiers($page);
        $primaryIdentifier = $this->getElementPrimaryVariationName();

        $secondaryIdentifiers = array_diff($identifiers, [
            $primaryIdentifier,
        ]);

        return array_values($secondaryIdentifiers);
    }

    /**
     * Filter down element controllers to only those which are relevant for our content variation
     *
     * @param SS_List $controllers
     * @param string $identifier
     * @return SS_List
     */
    protected function filterControllers(SS_List $controllers, string $identifier): SS_List
    {
        return $controllers->filterByCallback(
            function (ElementController $controller) use ($identifier): bool {
                /** @var BaseElement|VariationIdentifierElementExtension $block */
                $block = $controller->getElement();

                $identifierData = $block->LaboratoryVariationIdentifier;

                if (!$identifierData) {
                    // Content variation is not active on this block, so we will add it to the result
                    return true;
                }

                $identifiers = $this->unSerialiseIdentifierData($identifierData);

                // Return only blocks that contain matching identifiers
                return in_array($identifier, $identifiers);
            }
        );
    }

    /**
     * Filter down models to only those which are relevant for our content variation
     *
     * @param SS_List $list
     * @param string $identifier
     * @return SS_List
     */
    protected function filterList(SS_List $list, string $identifier): SS_List
    {
        $dataClass = $list->dataClass();

        // We are not able to validate what kind of model are wwe supposed to filter
        if (!$dataClass) {
            return $list;
        }

        $model = DataObject::singleton($dataClass);

        // Model doesn't have the variation identifier metadata, so we can't filter it
        if (!$model->hasExtension(VariationIdentifierModelExtension::class)) {
            return $list;
        }

        // TODO this filtering will not work correctly if identifiers aren't unique in a sense
        // that some identifiers contain other identifiers
        // This can be changed to model level filtering but that's potentially less performant
        // Alternatively, we could add value separators
        return $list->filterAny([
            // Content variation is not active on this model, so we will add it to the result
            'LaboratoryVariationIdentifier' => [
                null,
                '',
            ],
            // Return only models that contain matching identifiers
            'LaboratoryVariationIdentifier:PartialMatch' => [
                $identifier,
            ],
        ]);
    }

    /**
     * @param SiteTree $page
     * @return array
     * @throws Exception
     */
    protected function getValidIdentifiers(SiteTree $page): array
    {
        $cacheKey = $page->getUniqueKey();

        if (!array_key_exists($cacheKey, $this->validIdentifiers)) {
            $this->validIdentifiers[$cacheKey] = $this->findValidIdentifiers($page);
        }

        return $this->validIdentifiers[$cacheKey];
    }

    protected function findValidIdentifiers(SiteTree $page): array
    {
        // Initially add any identifiers from regular content blocks (if present)
        $identifiers = $this->findValidIdentifiersFromBlockPage($page);

        // Include any identifiers from generic models
        $lists = [];
        $page->invokeWithExtensions('collectLaboratoryContentLists', $lists);

        foreach ($lists as $list) {
            // Not a valid list, so we won't process it
            if (!$list instanceof SS_List) {
                continue;
            }

            $listIdentifiers = $this->findValidIdentifiersFromList($list);

            // No identifiers were found, so we can move on
            if (count($listIdentifiers) === 0) {
                continue;
            }

            // Add found identifier to our global list, so we can collect them all
            $identifiers = array_merge($identifiers, $listIdentifiers);
        }

        return $this->formatIdentifiers($identifiers);
    }

    /**
     * @param SiteTree|ElementalAreasExtension $page
     * @return array
     */
    protected function findValidIdentifiersFromBlockPage(SiteTree $page): array
    {
        $enabled = $this->getElementVariationEnabled();

        // Feature is not enabled so we don't have to collect the identifiers
        if (!$enabled) {
            return [];
        }

        // Page doesn't have any content blocks on it
        if (!$page->hasExtension(ElementalAreasExtension::class)) {
            return [];
        }

        $area = $page->ElementalArea();

        /** @var HasManyList $blocks */
        $blocks = $area->Elements();

        // We only want variant blocks in this case, so excluding any non-variation blocks
        $blocks = $blocks->filter([
            'LaboratoryVariationIdentifier:Not' => [
                null,
                '',
            ],
        ]);

        $identifiers = [];

        /** @var BaseElement|VariationIdentifierElementExtension $block */
        foreach ($blocks as $block) {
            $identifierData = $block->LaboratoryVariationIdentifier;

            // We don't have any identifier data
            if (!$identifierData) {
                continue;
            }

            // Parse out segments so we can add them to the list
            $identifierSegments = $this->unSerialiseIdentifierData($block->LaboratoryVariationIdentifier);
            $identifiers = array_merge($identifiers, $identifierSegments);
        }

        // Make sure we only have unique list of identifiers
        return $this->formatIdentifiers($identifiers);
    }

    protected function findValidIdentifiersFromList(SS_List $list): array
    {
        $dataClass = $list->dataClass();

        // We are not able to validate what kind of model are wwe supposed to filter
        if (!$dataClass) {
            return [];
        }

        $model = DataObject::singleton($dataClass);

        // Model doesn't have the variation identifier metadata, so we can't extract identifiers
        if (!$model->hasExtension(VariationIdentifierModelExtension::class)) {
            return [];
        }

        // We only want variant models in this case, so excluding any non-variation models
        $list = $list->filter([
            'LaboratoryVariationIdentifier:Not' => [
                null,
                '',
            ],
        ]);

        $identifiers = [];

        /** @var DataObject|VariationIdentifierElementExtension $item */
        foreach ($list as $item) {
            $identifierData = $item->LaboratoryVariationIdentifier;

            // We don't have any identifier data
            if (!$identifierData) {
                continue;
            }

            // Parse out segments so we can add them to the list
            $identifierSegments = $this->unSerialiseIdentifierData($item->LaboratoryVariationIdentifier);
            $identifiers = array_merge($identifiers, $identifierSegments);
        }

        // Make sure we only have unique list of identifiers
        return $this->formatIdentifiers($identifiers);
    }

    protected function unSerialiseIdentifierData(?string $identifier): array
    {
        if (!$identifier) {
            return [];
        }

        return explode(PHP_EOL, $identifier);
    }

    protected function serialiseIdentifierData(array $identifier): string
    {
        return implode(PHP_EOL, $identifier);
    }

    /**
     * Sanitise an identifier by only allowing alphabetic, hyphen and underscore characters
     *
     * @param string|null $identifier
     * @return string|null
     */
    protected function sanitiseIdentifier(?string $identifier): ?string
    {
        if (!$identifier) {
            // Value is empty, so we can just bail out as there is nothing to sanitise
            return $identifier;
        }

        // We only want to allow alphabetic, hyphen and underscore characters
        $sanitisedValue = preg_replace('/[^A-Za-z0-9-_]/', '', $identifier);

        // Only allow lower case as targeting identifier to keep to previous behaviour
        return mb_strtolower($sanitisedValue);
    }

    /**
     * Format list of identifiers to a consistent format (remove duplicates, sort)
     *
     * @param array $identifiers
     * @return array
     */
    protected function formatIdentifiers(array $identifiers): array
    {
        // Sort identifiers & remove duplicates
        $identifiers = array_unique($identifiers);
        sort($identifiers, SORT_STRING);
        $primaryIdentifier = $this->getElementPrimaryVariationName();

        // Always put primary variation first
        if (in_array($primaryIdentifier, $identifiers)) {
            $identifiers = array_diff($identifiers, [
                $primaryIdentifier,
            ]);
            array_unshift($identifiers, $primaryIdentifier);
        }

        return $identifiers;
    }
}
