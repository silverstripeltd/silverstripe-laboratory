<?php

namespace SilverStripe\Laboratory\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Laboratory\Service\TrafficSplitService;

/**
 * Extracts content variation related GET param and sets the global state of related service
 * which is used for later processing
 * This allows us to change the page render to only show blocks relevant to specific content variation
 */
class TrafficSplitMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate): mixed
    {
        $service = TrafficSplitService::singleton();

        // Elemental content variation is disabled
        if (!$service->getElementVariationEnabled()) {
            return $delegate($request);
        }

        $paramName = $service->getTrafficSplitMarker();

        // We don't have the param name, so we are unable to determine content variation
        if (!$paramName) {
            return $delegate($request);
        }

        $identifier = $request->getVar($paramName);

        // We found a content variation identifier so we're going to store it in the global state for later use
        if ($identifier) {
            $service->setContentVariationIdentifier((string) $identifier);
        }

        return $delegate($request);
    }
}
