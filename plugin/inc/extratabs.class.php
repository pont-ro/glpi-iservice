<?php

use Glpi\Application\View\TemplateRenderer;

class PluginIserviceExtraTabs extends CommonDBTM
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item::getType()) {
        case CartridgeItem::getType():
        case PrinterModel::getType():
            return  _t('iService');
                break;
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item::getType()) {
        case CartridgeItem::getType():
            /* @var CartridgeItem $item;*/
            self::displayTabContentForCartridgeItem($item);
            break;
        case PrinterModel::getType():
            /* @var PrinterModel $item;*/
            self::displayTabContentForPrinterModel($item);
            break;
        }

        return true;
    }

    private static function displayTabContentForCartridgeItem(CartridgeItem $item)
    {
        global $CFG_PLUGIN_ISERVICE, $CFG_GLPI;

        $printerModel = new PrinterModel();
        $cartridgeitemPrintermodel = new CartridgeItem_PrinterModel();

        echo TemplateRenderer::getInstance()->render(
            '@iservice/tabs/cartridgeitem-iservice.html.twig',
            [
                'plugin_url_base' => $CFG_PLUGIN_ISERVICE['root_doc'],
                'glpi_url_base' => $CFG_GLPI['root_doc'],
                'cartridgeItem' => $item,
                'allPrinterModels' => array_values($printerModel->find([], ['name'])),
                'selectedPrinterModelIds' => array_column($cartridgeitemPrintermodel->find(['cartridgeitems_id' =>  $item->getID()]), 'printermodels_id'),
            ]
        );
    }

    private static function displayTabContentForPrinterModel(CommonGLPI|PrinterModel $item)
    {
        echo "<div id='iservice_tab_container'>";
        echo PluginIservicePrinterModel::getIserviceTabHtml($item);
        echo "</div>";
    }

}
