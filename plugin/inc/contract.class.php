<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

class PluginIserviceContract extends Contract
{
    use PluginIserviceItem;

    /*
     *
     * @var PluginFieldsContractcontractcustomfield
     */
    public $customfields = null;

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display(
            "@iservice/pages/management/iservicecontract.html.twig", [
                'item'   => $this,
                'params' => $options,
            ]
        );
        return true;
    }

    public function getTypeClass(): ?string
    {
        return 'ContractType';
    }

    public function getTypeForeignKeyField(): ?string
    {
        return 'contracttypes_id';
    }

    public function getCustomFieldsModelName(): string
    {
        return 'PluginFieldsContractcontractcustomfield';
    }

}
