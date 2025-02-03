<?php

namespace SilverStripe\Laboratory\Extension;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Laboratory\Service\TrafficSplitService;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\ORM\ValidationException;

/**
 * @method ElementalArea|$this getOwner()
 */
class TrafficSplitElementalAreaExtension extends Extension
{
    /**
     * Adds traffic split capability to the elemental area
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

        // Elemental content variation is disabled
        if (!TrafficSplitService::singleton()->getElementVariationEnabled()) {
            return $area->ElementControllers();
        }

        // Page doesn't have elemental area for some reason, fall back to the parent method
        if (!$page->hasExtension(ElementalAreasExtension::class)) {
            return $area->ElementControllers();
        }

        $blocks = $area->Elements();

        if ($blocks instanceof UnsavedRelationList) {
            return ArrayList::create();
        }

        // Apply access right filter (same as parent method)
        $blocks = $blocks->filterByCallback(static function (BaseElement $block) {
            return $block->canView();
        });

        $controllers = ArrayList::create()->setDataClass(ElementController::class);

        /** @var BaseElement $block */
        foreach ($blocks as $block) {
            $controller = $block->getController();
            $controllers->push($controller);
        }

        return TrafficSplitService::singleton()->getElementControllersWithTrafficSplit($controllers, $page);
    }
}
