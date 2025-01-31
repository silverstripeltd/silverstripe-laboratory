<?php

namespace SilverStripe\Laboratory\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Laboratory\Model\ContentVariation;
use SilverStripe\Laboratory\Model\TrafficSplit;
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
    /**
     * @var string
     * @config
     */
    private static string $traffic_split_marker = 'laboratory-traffic-split';

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
        $owner = $this->getOwner();
        $trafficSplit = $this->getActiveTrafficSplit();

        if (!$trafficSplit) {
            return '';
        }

        $variationData = [];
        $marker = (string) $owner->config()->get('traffic_split_marker');
        $contentVariations = $trafficSplit->ContentVariations();

        foreach ($contentVariations as $contentVariation) {
            $pageUrl = $contentVariation
                ->VariationPage()
                ->AbsoluteLink();
            $markerSegment = sprintf('?%s=%s', $marker, $contentVariation->ID);
            $variationUrl = Controller::join_links($pageUrl, $markerSegment);

            $variationData[] = [
                'probability' => $contentVariation->Probability,
                'url' => $variationUrl,
            ];
        }

        $data = [
            'marker' => $marker,
            'expiry' => $trafficSplit->VariationChoiceExpiry,
            'variations' => $variationData,
        ];

        $payload = json_encode($data, JSON_FORCE_OBJECT);
        $snippet = <<<'HTML'
<script type="text/javascript">
    const trafficSplitPayload = %s;

    // Extract GET params
    const segments = window.location.search.substr(1).split('&');
    let markerPresent = false;

    for (var i = 0; i < segments.length; i++) {
        const segment = segments[i];
        const paramName = segment.search('=') ? segment.split('=').shift() : segment;

        // We are looking for a specific GET param
        if (paramName !== trafficSplitPayload.marker) {
            continue;
        }

        // We found our marker
        markerPresent = true;

        break;
    }

    // Redirect user if marker is present
    if (!markerPresent) {
        // TODO remember the past choice
        const randomKey = Math.floor(Math.random() * 100);
        let index = randomKey;
        let targetUrl = '';

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

            break;
        }

        if (targetUrl) {
          window.location.href = targetUrl;
        }
    }
</script>
HTML;

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
}
