<?php

namespace SilverStripe\Laboratory\Extension;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;

/**
 * Provide the capability to render content variations
 *
 * @method ElementalArea|$this getOwner()
 */
class TrafficSplitElementalAreaExtension extends Extension
{
    /**
     * Adds traffic split capability to the elemental area as we don't have any extension points
     * This is an enhanced version of @see ElementalArea::ElementControllers()
     *
     * @return SS_List
     * @throws NotFoundExceptionInterface
     * @throws ValidationException
     * @throws Exception
     */
    public function ElementControllersWithTrafficSplit(): SS_List
    {
        $area = $this->getOwner();
        $page = $area->getOwnerPage();

        $controllers = $area->ElementControllers();

        // Elemental content variation is disabled
        if (!TrafficSplitService::singleton()->getElementVariationEnabled()) {
            return $controllers;
        }

        // Page doesn't have elemental area for some reason, fall back to the parent method
        if (!$page || !$page->hasExtension(ElementalAreasExtension::class)) {
            return $controllers;
        }

        return TrafficSplitService::singleton()->getElementControllersWithTrafficSplit($controllers, $page);
    }
}
