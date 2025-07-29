<?php

namespace SilverStripe\Laboratory\Extension;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Laboratory\Model\ContentVariation;
use SilverStripe\Laboratory\Model\TrafficSplit;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\View\SSViewer;

/**
 * Provides template methods for pages to output traffic split related JS code
 *
 * @method HasManyList|TrafficSplit[] LaboratoryTrafficSplits()
 * @method HasManyList|ContentVariation[] LaboratoryContentVariations()
 * @method SiteTree|$this getOwner()
 */
class TrafficSplitSiteTreeExtension extends Extension
{
    private static array $has_many = [
        'LaboratoryTrafficSplits' => TrafficSplit::class . '.EntryPage',
        'LaboratoryContentVariations' => ContentVariation::class . '.VariationPage',
    ];

    private static array $casting = [
        'TrafficSplitSnippet' => 'HTMLFragment',
    ];

    /**
     * Template method
     *
     * @return string
     */
    public function getTrafficSplitSnippet(): string
    {
        // Disable this feature if a page is viewed from within the CMS preview
        if ($this->isCmsPreview()) {
            return '';
        }

        $trafficSplit = $this->getActiveTrafficSplit();

        if (!$trafficSplit) {
            return '';
        }

        $service = TrafficSplitService::singleton();
        $variationData = [];
        $marker = $service->getTrafficSplitMarker();
        $contentVariations = $trafficSplit->ContentVariations();

        foreach ($contentVariations as $contentVariation) {
            // If we have a content variation identifier let's use it, otherwise fall back to content variation ID
            $markerValue = $contentVariation->VariationIdentifier ?: sprintf('cv-%d', $contentVariation->ID);
            $pageUrl = $contentVariation
                ->VariationPage()
                ->AbsoluteLink();
            $markerSegment = sprintf('?%s=%s', $marker, $markerValue);
            $variationUrl = Controller::join_links($pageUrl, $markerSegment);

            $variationData[] = [
                'probability' => $contentVariation->Probability,
                'url' => $variationUrl,
                'id' => $markerValue,
            ];
        }

        // Make sure cookie name has a distinct key so subsequent changes to traffic split
        // will cause a new cookie name to be issues
        $cookieName = $service->getTrafficSplitCookieName();
        $cookieData = sprintf('%s-%d-%d', $cookieName, $trafficSplit->ID, $trafficSplit->Version);
        $activationEndDate = $trafficSplit->getActivationEndDate();
        $cookieExpiry = $this->formatCookieExpiry($activationEndDate);

        $data = [
            'marker' => $marker,
            'cookie' => $cookieData,
            'expiry' => $cookieExpiry,
            'variations' => $variationData,
        ];

        $payload = json_encode($data, JSON_FORCE_OBJECT);
        $snippet = $this->getCodeTemplate();

        return sprintf($snippet, $payload);
    }

    /**
     * Add laboratory content variations widget
     *
     * @param FieldList $actions
     * @return void
     * @throws Exception
     */
    protected function updateCMSActions(FieldList $actions): void
    {
        /** @var Tab $moreOptions */
        $moreOptions = $actions->fieldByName('ActionMenus.MoreOptions');

        // UI widget is not available so we don't have a container to add fields to
        if (!$moreOptions) {
            return;
        }

        $owner = $this->getOwner();
        $service = TrafficSplitService::singleton();
        $identifiers = $service->getSecondaryVariationIdentifiers($owner);

        // The page has no secondary identifiers so there is nothing to display
        if (count($identifiers) === 0) {
            return;
        }

        // Generate content variation links
        $marker = $service->getTrafficSplitMarker();
        $pageUrl = $owner->AbsoluteLink();
        $links = ArrayList::create();

        foreach ($identifiers as $identifier) {
            $markerSegment = sprintf('?%s=%s', $marker, $identifier);
            $variationUrl = Controller::join_links($pageUrl, $markerSegment);
            $links->push(ArrayData::create([
                'Identifier' => $identifier,
                'Link' => $variationUrl,
            ]));
        }

        // Render the widget with a template
        $widgetTemplate = SSViewer::get_templates_by_class(
            $owner::class,
            '_ContentVariationsWidget',
            SiteTree::class
        );
        $content = $owner->customise([
            'ContentVariationLinks' => $links,
        ])->renderWith($widgetTemplate);

        $contentVariationsLinksField = LiteralField::create('LaboratoryContentVariationLinks', $content);
        $moreOptions->push($contentVariationsLinksField);
    }

    protected function getActiveTrafficSplit(): ?TrafficSplit
    {
        $owner = $this->getOwner();

        foreach ($owner->LaboratoryTrafficSplits() as $trafficSplit) {
            if (!$trafficSplit->isPublished()) {
                continue;
            }

            return $trafficSplit;
        }

        return null;
    }

    protected function isCmsPreview(): bool
    {
        $controller = Controller::curr();

        if (!$controller) {
            return false;
        }

        $request = $controller->getRequest();

        if (!$request) {
            return false;
        }

        return $request->getVar('CMSPreview') === '1';
    }

    /**
     * Format the datetime of the end of the traffic split active period
     *
     * @param DBDatetime $expiry
     * @return string
     */
    protected function formatCookieExpiry(DBDatetime $expiry): string
    {
        $formatter = $expiry->getFormatter();
        $formatter->setPattern(DBDatetime::ISO_DATETIME_NORMALISED);

        return $formatter->format($expiry->getTimestamp());
    }

    protected function getCodeTemplate(): string
    {
        return <<<'HTML'
<script type="text/javascript">
    const trafficSplitPayload = %s;

    // Extract GET params
    const segments = window.location.search.substr(1).split('&');
    let markerPresent = false;

    for (var i = 0; i < segments.length; i++) {
        const segment = segments[i];
        const itemName = segment.search('=') ? segment.split('=').shift() : segment;

        // We are looking for a specific GET param
        if (itemName !== trafficSplitPayload.marker) {
            continue;
        }

        // We found our marker
        markerPresent = true;

        break;
    }

    // Redirect user if marker is present
    if (!markerPresent) {
        // Parse cookies from raw data
        const cookieName = trafficSplitPayload.cookie;
        const cookies = document.cookie.replace('; ', ';').split(';');

        // Search for a specific cookie
        let preselectedVariation = '';

        for (var i = 0; i < cookies.length; i++) {
            const segment = cookies[i];

            // Cookie is missing a value
            if (!segment.search('=')) {
              continue;
            }

            const [itemName, itemValue] = segment.split('=');

            // We are looking for a specific cookie
            if (itemName !== cookieName) {
                continue;
            }

            // We found our marker
            preselectedVariation = itemValue;

            break;
        }

        let targetUrl = '';

        // If we have a pre-selected variation ID, let's try to find the variation data so we can redirect to it
        if (preselectedVariation) {
            for (var key in trafficSplitPayload.variations) {
                const variation = trafficSplitPayload.variations[key];

                // We didn't choose this variation - keep looking
                if (preselectedVariation !== variation.id) {
                  continue;
                }

                // We have found the chosen variation
                targetUrl = variation.url;

                break;
            }
        }

        // If we still don't have a content variation chosen, we will do so randomly
        if (!targetUrl) {
            const randomKey = Math.floor(Math.random() * 100);
            let index = randomKey;

            for (var key in trafficSplitPayload.variations) {
                const variation = trafficSplitPayload.variations[key];
                const probability = variation.probability;

                // We didn't choose this variation - keep looking
                if (index >= probability) {
                    // Move the index to the next variation
                    index -= probability;

                    continue;
                }

                // We have found the chosen variation
                targetUrl = variation.url;

                // Set cookie to remember this choice
                const rawExpiryDate = new Date(trafficSplitPayload.expiry);
                const cookieExpiryDate = rawExpiryDate.toUTCString();
                const cookieData = `${cookieName}=${variation.id}; expires=${cookieExpiryDate}`;
                document.cookie = cookieData;

                break;
            }
        }

        if (targetUrl) {
          // Perform redirect
          window.location.href = targetUrl;
        }
    }
</script>
HTML;
    }
}
