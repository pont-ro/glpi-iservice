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

    public static $customFieldsModelName = 'PluginFieldsContractcontractcustomfield';

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

    public static function dropdown($options = [])
    {
        // !!! Copied from Glpi 10.0.20, update if newer Glpi is used !!!

        /** @var \DBmysql $DB */
        global $DB;

        $p = [
            'name'           => 'contracts_id',
            'value'          => '',
            'entity'         => '',
            'rand'           => mt_rand(),
            'entity_sons'    => false,
            'used'           => [],
            'nochecklimit'   => false,
            'on_change'      => '',
            'display'        => true,
            'expired'        => false,
            'toadd'          => [],
            'class'          => "form-select",
            'width'          => "",
            'hide_if_no_elements' => false,
        ];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $WHERE = [];
        if (count($p['used'])) {
            $WHERE['NOT'] = ['glpi_contracts.id' => $p['used']];
        }
        if (!$p['expired']) {
            $WHERE[] = self::getExpiredCriteria();
        }

        $iterator = $DB->request([
            'SELECT'    => 'glpi_contracts.*',
            'FROM'      => 'glpi_contracts',
            'WHERE'     => array_merge([
                'glpi_contracts.is_deleted'   => 0,
                'glpi_contracts.is_template'  => 0,
            ], $WHERE),
            'ORDERBY'   => [
                'glpi_contracts.name ASC',
                'glpi_contracts.begin_date DESC',
            ],
        ]);

        if ($p['hide_if_no_elements'] && $iterator->count() === 0) {
            return;
        }

        $contract_items = array_column(PluginIserviceDB::getQueryResult("
            select c.id, count(c.id) items
            from glpi_contracts c
            join glpi_contracttypes ct on ct.id = c.contracttypes_id and ct.name = 'Gsm'
            join glpi_contracts_items ci on ci.contracts_id = c.id
            where c.is_deleted = 0 and c.is_template = 0
            group by c.id;
        "), 'items', 'id');

        $group  = '';
        $prev   = -1;
        $values = $p['toadd'];
        foreach ($iterator as $data) {
            if (
                $p['nochecklimit']
                || ($data["max_links_allowed"] == 0)
                || ($data["max_links_allowed"] > countElementsInTable(
                        'glpi_contracts_items',
                        ['contracts_id' => $data['id']]
                    ))
            ) {
                if ($data["entities_id"] != $prev) {
                    $group = Dropdown::getDropdownName("glpi_entities", $data["entities_id"]);
                    $prev = $data["entities_id"];
                }

                $name = $data["name"];
                if (
                    $_SESSION["glpiis_ids_visible"]
                    || empty($data["name"])
                ) {
                    $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                }

                $tmp = sprintf(__('%1$s - %2$s'), $name, $data["num"]);
                $tmp = sprintf(__('%1$s - %2$s'), $tmp, Html::convDateTime($data["begin_date"]));

                if ($p['markusedforgsm'] && ($contract_items[$data['id']] ?? 0) > 0) {
                    $tmp = sprintf('%1$s: %2$s', __('Used', 'iservice'), $tmp);
                }

                $values[$group][$data['id']] = $tmp;
            }
        }
        return Dropdown::showFromArray($p['name'], $values, [
            'value'               => $p['value'],
            'on_change'           => $p['on_change'],
            'display'             => $p['display'],
            'display_emptychoice' => true,
            'class'               => $p['class'],
            'width'               => $p['width'],
        ]);
    }
}
