<?php

namespace SilverStripe\Laboratory\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Laboratory\Model\ContentVariation;
use SilverStripe\Laboratory\Model\TrafficSplit;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\HasManyList;

/**
 * Provides template methods for pages to output traffic split related JS code
 *
 * @method HasManyList|TrafficSplit[] LaboratoryTrafficSplits()
 * @method HasManyList|ContentVariation[] LaboratoryContentVariations()
 *  @method SiteTree|$this getOwner()
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
            $markerValue = $contentVariation->VariationIdentifier ?: $contentVariation->ID;
            $pageUrl = $contentVariation
                ->VariationPage()
                ->AbsoluteLink();
            $markerSegment = sprintf('?%s=%s', $marker, $markerValue);
            $variationUrl = Controller::join_links($pageUrl, $markerSegment);

            $variationData[] = [
                'probability' => $contentVariation->Probability,
                'url' => $variationUrl,
                'id' => $contentVariation->ID,
            ];
        }

        // Make sure cookie name has a distinct key so subsequent changes to traffic split
        // will cause a new cookie name to be issues
        $cookieName = $service->getTrafficSplitCookieName();
        $cookieData = sprintf('%s-%d-%d', $cookieName, $trafficSplit->ID, $trafficSplit->Version);

        $data = [
            'marker' => $marker,
            'cookie' => $cookieData,
            'expiry' => $trafficSplit->getActivationEndDate(),
            'variations' => $variationData,
        ];

        $payload = json_encode($data, JSON_FORCE_OBJECT);
        $snippet = $this->getCodeTemplate();

        return sprintf($snippet, $payload);
    }

    private function getActiveTrafficSplit(): ?TrafficSplit
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

    private function isCmsPreview(): bool
    {
        if (Controller::has_curr()) {
            return false;
        }

        $request = Controller::curr()?->getRequest();

        if (!$request) {
            return false;
        }

        return $request->getVar('CMSPreview') === '1';
    }

    private function getCodeTemplate(): string
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
        let preselectedVariation = 0;

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
            preselectedVariation = parseInt(itemValue);

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
                document.cookie = `${cookieName}=${variation.id}; expires=${cookieExpiryDate}; Secure`;

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
