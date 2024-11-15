<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIservicePrinterModel extends PrinterModel
{
    use PluginIserviceItem;

    /*
     * @var PluginFieldsPrintermodelprintermodelcustomfield
     */
    public $customfields = null;

    public static function getTable($classname = null): string
    {
        return PrinterModel::getTable($classname);
    }

    public function getAssignedCartridgeItems($dropdown_options = []): bool|array
    {
        $query = "SELECT COUNT(*) AS cpt
                      , ci.id
                      , ci.name
                      , ci.ref
                  FROM glpi_cartridgeitems ci
                  INNER JOIN glpi_cartridgeitems_printermodels cip ON cip.cartridgeitems_id = ci.id
                  WHERE ci.is_deleted = 0 AND cip.printermodels_id = '" . $this->getID() . "'
                  GROUP BY ci.id
                  ";

        if (empty($dropdown_options['order_by'])) {
            $query .= "ORDER BY ci.name, ci.ref";
        } else {
            $query .= "ORDER BY $dropdown_options[order_by]";
        }

        return PluginIserviceDB::getQueryResult($query);
    }

    public static function getIserviceTabHtml(CommonGLPI|PrinterModel $item): string
    {
        global $CFG_PLUGIN_ISERVICE, $CFG_GLPI;

        $pluginIservicePrinterModel = new PluginIservicePrinterModel();
        $pluginIservicePrinterModel->getFromDB($item->getID());
        $assignedCartidgeItems = $pluginIservicePrinterModel->getAssignedCartridgeItems();

        return TemplateRenderer::getInstance()->render(
            '@iservice/tabs/printermodel-iservice.html.twig',
            [
                'plugin_url_base' => $CFG_PLUGIN_ISERVICE['root_doc'],
                'glpi_url_base' => $CFG_GLPI['root_doc'],
                'printerModel' => $item,
                'assignedCartidgeItems' => $assignedCartidgeItems,
            ]
        );
    }

    public static function ajaxAddCartridgeItem()
    {
        list($printerModelId, $cartridgeItemIds, $pluginIservicePrinterModel) = self::prepareVarsForAjaxRequestHandling();

        if (empty($printerModelId) || empty($cartridgeItemIds[0] ?? null)) {
            return self::getJsonResponse('error', _t('An error occurred!'));
        }

        $query = "INSERT INTO glpi_cartridgeitems_printermodels (printermodels_id, cartridgeitems_id)
                          VALUES ('$printerModelId', '$cartridgeItemIds[0]' )";

        if (PluginIserviceDB::getQueryResult($query)) {
            return self::getJsonResponse('success', _t('Cartridge Item added'), self::getIserviceTabHtml($pluginIservicePrinterModel));
        } else {
            return self::getJsonResponse('error', _t('Error adding Cartridge Item'));
        }
    }

    public static function ajaxRemoveCartridgeItems()
    {
        list($printerModelId, $cartridgeItemIds, $pluginIservicePrinterModel) = self::prepareVarsForAjaxRequestHandling();

        if (empty($printerModelId) || empty($cartridgeItemIds)) {
            return self::getJsonResponse('error', _t('An error occurred!'));
        }

        $query = "DELETE FROM glpi_cartridgeitems_printermodels
                  WHERE printermodels_id = '$printerModelId'
                  AND cartridgeitems_id IN (" . implode(',', $cartridgeItemIds) . ")";

        if (PluginIserviceDB::getQueryResult($query)) {
            return self::getJsonResponse('success', _t('Cartridge Item(s) removed'), self::getIserviceTabHtml($pluginIservicePrinterModel));
        } else {
            return self::getJsonResponse('error', _t('Error removing Cartridge Item(s)'));
        }

    }

    private static function prepareVarsForAjaxRequestHandling(): array
    {
        $printerModelId   = IserviceToolBox::getInputVariable('printerModelId');
        $cartridgeItemIds = IserviceToolBox::getArrayInputVariable('cartridgeItemIds');

        $pluginIservicePrinterModel = new PluginIservicePrinterModel();
        $pluginIservicePrinterModel->getFromDB($printerModelId);

        return [
            $printerModelId,
            $cartridgeItemIds,
            $pluginIservicePrinterModel,
        ];
    }

}
