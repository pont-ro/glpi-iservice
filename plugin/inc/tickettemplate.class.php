<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
class PluginIserviceTicketTemplate extends ITILTemplate
{
    public $hiddenInput = [];

    public function __construct()
    {
        parent::__construct();
        $this->hidden      = $this->getFieldsNotAvailableForCurrentProfile();
        $this->hiddenInput = $this->getHiddenInputFieldsForCurrentProfile();
    }

    public function isHiddenInput($field)
    {

        if (isset($this->hiddenInput[$field])) {
            return true;
        }

        return false;
    }

    private function getFieldsNotAvailableForCurrentProfile(): array
    {
        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            return [
                '_users_id_observer' => true,
                '_users_id_assign' => true,
                'sumOfUnpaidInvoicesLink' => true,
                'itilcategories_id' => true,
                '_followup[is_private]]' => true,
                'status' => true,
                '_operator_reading' => true,
                'without_paper_field' => true,
                'no_travel_field' => true,
                '_export_type' => true,
                'Consumables' => true, // Not an input, but a whole section, it is handled differently in ticket.html.twig..
                'effective_date_field' => true,
                '_followup_content' => true,
            ];
        }

        // These values are handled by parent isHiddenField() method in 'glpi\templates\components\form\fields_macros.html.twig' field macro, line 789. Fields are NOT rendered as <input type="hidden">, but are not rendered at all.
        return [];
    }

    private function getHiddenInputFieldsForCurrentProfile(): array
    {
        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            return [
                'suppliers_id' => true,
            ];
        }

        // Fields specified here should be rendered as <input type="hidden">, but there is no builtin functionality in GLPI to handle it, custom solution should be created.
        return [];
    }

}
