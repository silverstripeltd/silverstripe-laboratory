<?php

namespace SilverStripe\Laboratory\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Laboratory\Model\TrafficSplit;

class LaboratoryAdmin extends ModelAdmin
{
    private const string TRAFFIC_SPLIT_MANAGER_TAB = 'traffic-split-manager';

    private static string $url_segment = 'laboratory-admin';

    private static string $menu_title = 'Laboratory';

    private static string $menu_icon_class = 'font-icon-lamp';

    private static array $managed_models = [
        LaboratoryAdmin::TRAFFIC_SPLIT_MANAGER_TAB => [
            'title' => 'Traffic splits manager',
            'dataClass' => TrafficSplit::class,
        ],
    ];

    protected function getGridField(): GridField
    {
        $this->beforeExtending(
            'updateGridField',
            function (GridField $field): void {
                $this->applyTrafficSplitsManagerConfig($field);
            }
        );

        return parent::getGridField();
    }

    private function applyTrafficSplitsManagerConfig(GridField $gridField): void
    {
        $modelTab = $this->modelTab;

        if ($modelTab !== LaboratoryAdmin::TRAFFIC_SPLIT_MANAGER_TAB) {
            return;
        }

        $config = $gridField->getConfig();
        $config->removeComponentsByType([
            // Remove import and export button as we expect the models to be managed only in CMS
            GridFieldImportButton::class,
            GridFieldExportButton::class,
        ]);
    }
}
