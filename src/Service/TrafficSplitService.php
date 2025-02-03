<?php

namespace SilverStripe\Laboratory\Service;

use App\Pages\BlockPage;
use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Resettable;
use SilverStripe\Laboratory\Extension\VariationIdentifierElementExtension;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\SS_List;

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
        self::singleton()->clearData();
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

    public function getContentVariationIdentifier(): string
    {
        return $this->contentVariationIdentifier;
    }

    public function setContentVariationIdentifier(string $contentVariationIdentifier): self
    {
        $this->contentVariationIdentifier = $contentVariationIdentifier;

        return $this;
    }

    public function clearData(): self
    {
        $this->contentVariationIdentifier = '';
        $this->validIdentifiers = [];

        return $this;
    }

    /**
     * @param SS_List $controllers
     * @param BlockPage $page
     * @return SS_List
     * @throws Exception
     */
    public function getElementControllersWithTrafficSplit(SS_List $controllers, BlockPage $page): SS_List
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

        return array_diff($identifiers, [
            $primaryIdentifier,
        ]);
    }

    /**
     * Filter down element controllers to only those which are relevant for our content variation
     *
     * @param SS_List $controllers
     * @param string $identifier
     * @return SS_List
     */
    private function filterControllers(SS_List $controllers, string $identifier): SS_List
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
     * @param SiteTree $page
     * @return array
     * @throws Exception
     */
    private function getValidIdentifiers(SiteTree $page): array
    {
        $cacheKey = $page->getUniqueKey();

        if (!array_key_exists($cacheKey, $this->validIdentifiers)) {
            $this->validIdentifiers[$cacheKey] = $this->findValidIdentifiers($page);
        }

        return $this->validIdentifiers[$cacheKey];
    }

    /**
     * @param SiteTree|ElementalAreasExtension $page
     * @return array
     */
    private function findValidIdentifiers(SiteTree $page): array
    {
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

    private function unSerialiseIdentifierData(?string $identifier): array
    {
        if (!$identifier) {
            return [];
        }

        return explode(PHP_EOL, $identifier);
    }

    private function serialiseIdentifierData(array $identifier): string
    {
        return implode(PHP_EOL, $identifier);
    }

    /**
     * Sanitise an identifier by only allowing alphabetic, hyphen and underscore characters
     *
     * @param string|null $identifier
     * @return string|null
     */
    private function sanitiseIdentifier(?string $identifier): ?string
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
    private function formatIdentifiers(array $identifiers): array
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
