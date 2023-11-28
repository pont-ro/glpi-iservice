<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * Cartridge_Ticket Class
 *
 *  Relation between Tickets and Cartridges
 * */
class PluginIserviceCartridge_Ticket extends CommonDBRelation
{

    // From CommonDBRelation.
    public static $itemtype_1         = 'Ticket';
    public static $items_id_1         = 'tickets_id';
    public static $itemtype_2         = 'Cartridge';
    public static $items_id_2         = 'cartridges_id';
    public static $checkItem_2_Rights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public function getForbiddenStandardMassiveAction(): array
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem(): bool
    {

        $ticket = new Ticket();
        // Not item linked for closed tickets.
        if ($ticket->getFromDB($this->fields['tickets_id']) && in_array($ticket->fields['status'], $ticket->getClosedStatusArray())) {
            return false;
        }

        return parent::canCreateItem();
    }

    public function prepareInputForAdd($input): array
    {
        if (isset($input['locations_id']) && $input['locations_id'] < 0) {
            $input['locations_id'] = 0;
        }

        return parent::prepareInputForAdd($input);
    }

    public function getForTicket($id): array|bool
    {
        $query       = "SELECT ct.*, ci.name FROM " . $this->getTable() . " ct LEFT JOIN glpi_cartridges c ON c.id = ct.cartridges_id LEFT JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id WHERE " . self::$items_id_1 . " = $id ORDER BY id";
        $result_data = PluginIserviceDB::getQueryResult($query);
        return empty($result_data) ? false : $result_data;
    }

    public static function getDataForTicketCartrigesSection(Ticket $ticket, &$required_fields, $generate_form = true, $readonly = false)
    {
        global $DB;

        $id = $ticket->getID();

        $ticket_fields = $ticket->fields;
        if (!$ticket->can($id, READ)) {
            return false;
        }

        $canedit = !$readonly && $ticket->canEdit($id);
        $rand    = mt_rand();

        $ticket->fields = $ticket_fields;

        $cartridges = [];
        $used_ids   = [];

        $c_result = $DB->query(
            "SELECT ct.id IDD, ci.id cid, ct.locations_id, c.id, ci.name, ci.compatible_mercury_codes_field
                         FROM glpi_plugin_iservice_cartridges_tickets ct
                         LEFT JOIN glpi_cartridges c ON c.id = ct.cartridges_id
                         LEFT JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                         WHERE ct.tickets_id = $id ORDER BY ct.id"
        );
        if ($c_result) {
            while ($cartridge = $DB->fetchAssoc($c_result)) {
                $cartridges[$cartridge['id']] = $cartridge;
                $used_ids[$cartridge['cid']]  = $cartridge['cid'];
            }
        }

        $data = [];

        if ($canedit) {
            if ($generate_form) {
                $data['form'] = [
                    'thLabel' => __('Add an item'),
                    'name' => 'ticketitem_form' . $rand,
                    'id' => 'ticketitem_form' . $rand,
                    'method' => 'post',
                    'action' => Toolbox::getItemTypeFormURL(__CLASS__),
                ];
            }

            $data['addCartridgesSection'] = [
                'type' => 'table',
                'class' => 'add-cartridge-div',
                'inputs' => [
                    'cartridgesDropdown' => [
                        'type' => 'dropdownArray',
                        'comments' => false,
                        'name' => '_plugin_iservice_cartridge[cartridgeitems_id]',
                        'used' => $used_ids,
                        'on_change' => '$("[name=\'add_cartridge\']").click();',
                        'elementsArray' => PluginIserviceCartridgeItem::getCartridgesDropdownOptions($ticket),
                        'script' => '<script>
                            setTimeout(function() {                    
                                addRecurrentCheck(function() {
                                    if ($("[name=\\"_plugin_iservice_cartridge[cartridgeitems_id]\"]").val() != 0) {
                                        $("[name=\\"add_cartridge\\"]").click();$("#page").hide();
                                        return true;
                                    }
                                    return false;
                                });}, 1000);
                          </script>',
                    ],
                    'addButton' => [
                        'type' => 'submit',
                        'name' => 'add_cartridge',
                        'value' => _sx('button', 'Add'),
                        'class' => 'submit',
                    ],
                    'cartridgesAmount' => [
                        'type' => 'text',
                        'name' => '_plugin_iservice_cartridge[amount]',
                        'class' => 'cartridges-amount',
                        'value' => '1',
                    ],
                    'cartridgeLocationDropdown' => [
                        'type' => 'dropdown',
                        'name' => '_plugin_iservice_cartridge[locations_id]',
                        'label' => __('Location'),
                        'itemType' => 'Location',
                        'display' => false,
                        'comments' => false,
                    ],
                ],
            ];
        }

        if (count($cartridges) < 1) {
            $data['cartridges'] = $cartridges;
            return $data;
        }

        $data['cartridgesTableSection'] = [
            'type' => 'table',
            'header' => [
                'checkbox' => [
                    'hidden' => !$canedit,
                    'value' => '',
                ],
            ],
            'name' => [
                'value' => __('Name'),
            ],
            'location' => [
                'value' => __('Location'),
            ],
        ];

        foreach ($cartridges as $key => $cartridge) {
            $data['cartridgesTableSection']['rows'][$key] = [
                'cols' => [
                    'checkbox' => [
                        'hidden' => !$canedit,
                        'toRefactor' => Html::getCheckbox(
                            [
                                'name' => "_plugin_iservice_cartridges_tickets[$cartridge[IDD]]",
                                'zero_on_empty' => false,
                            ]
                        ),
                    ],
                    'name' => [
                        'value' => $cartridge['name'],
                    ],
                    'location' => [
                        'toRefactor' => Dropdown::getDropdownName('glpi_locations', $cartridge['locations_id']),
                    ],
                ],
            ];
        }

        if ($canedit) {
            $data['cartridgesTableSection']['buttons']['removeButton'] = [
                'type' => 'submit',
                'name' => 'remove_cartridge',
                'value' => __('Delete'),
                'class' => 'submit',
            ];
        }

        return $data;
    }

    public static function getDataForTicketChangeableSection(PluginIserviceTicket $ticket, &$required_fields, $generate_form = true, $readonly = false): array
    {
        global $DB;

        $id = $ticket->getID();

        $ticket_fields = $ticket->fields;
        if (!$ticket->can($id, READ)) {
            return false;
        }

        $canEdit = !$readonly && $ticket->canEdit($id);
        $rand    = mt_rand();

        $ticket->fields = $ticket_fields;

        $cartridges = [];
        $used_ids   = [];
        $used       = [];

        $c_result = $DB->query(
            "SELECT 
                      ct.id IDD
                    , ct.plugin_fields_cartridgeitemtypedropdowns_id selected_type_id
                    , ct.cartridges_id_emptied
                    , c.id
                    , ci.id cid
                    , ci.name
                    , ci.mercury_code_field mercurycode
                    , ci.compatible_mercury_codes_field mercurycodes
                    , ci.supported_types_field supportedtypes
                    , l.name location_name
                    , l.completename location_completename
                    , p.id pid
                    , p.name printer_name
                    , c.date_use
                    , c.date_out
                 FROM glpi_plugin_iservice_cartridges_tickets ct
                 INNER JOIN glpi_plugin_iservice_cartridges c ON c.id = ct.cartridges_id
                 INNER JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                 LEFT JOIN glpi_locations l ON l.id = c.locations_id_field
                 LEFT JOIN glpi_printers p ON p.id = c.printers_id
                 WHERE ct.tickets_id = $id ORDER BY ct.id"
        );

        if ($c_result) {
            while ($cartridge = $DB->fetchAssoc($c_result)) {
                $cartridges[$cartridge['id']]               = $cartridge;
                $used_ids[$cartridge['cid']]                = $cartridge['cid'];
                $used[$cartridge['cid']]['last']            = $cartridge['id'];
                $used[$cartridge['cid']]['mercurycodes']    = $cartridge['mercurycodes'];
                $used[$cartridge['cid']]['supported_types'] = $cartridge['supportedtypes'];
                if (!empty($cartridge['selected_type_id'])) {
                    $used[$cartridge['cid']]['types'][] = $cartridge['selected_type_id'];
                }
            }

            foreach ($used as $cid => $used_data) {
                if ($used_data['supported_types'] !== implode(',', empty($used_data['types']) ? [] : $used_data['types'])) {
                    unset($used_ids[$cid]);
                }
            }
        }

        $data = [];

        if ($canEdit) {
            if ($generate_form) {
                $data['form'] = [
                    'thLabel' => __('Add an item'),
                    'name'    => "ticketitem_form$rand",
                    'id'      => "ticketitem_form$rand",
                    'method'  => 'post',
                    'action'  => Toolbox::getItemTypeFormURL(__CLASS__),
                ];
            }

            $data['addItemsSection'] = [
                'type'   => 'table',
            ];

            $changeableDropdownSection = PluginIserviceCartridgeItem::getChangeableDropdownDataForTicket(
                $ticket, ['used' => $used_ids, 'used_data' => $used ]
            );

            $data['addItemsSection']['notInstalledCartridgesTable'] = $changeableDropdownSection['notInstalledCartridgesTable'] ?? null;

            $data['addItemsSection']['warning']              = $changeableDropdownSection['warning'] ?? null;
            $data['addItemsSection']['cartridgeInstallDate'] = $changeableDropdownSection['cartridgeInstallDate'] ?? null;

            if (!empty($changeableDropdownSection['hiddenInput'])) {
                $data['addItemsSection']['inputs']['hiddenInput'] = $changeableDropdownSection['hiddenInput'];
            }

            if (!empty($changeableDropdownSection['elementsArray'])) {
                $data['addItemsSection']['inputs']['changeableDropdown'] = [
                    'order'         => 1,
                    'type'          => 'dropdownArray',
                    'elementsArray' => $changeableDropdownSection['elementsArray'],
                    'comments'      => false,
                    'name'          => '_plugin_iservice_cartridge[cartridgeitems_id]',
                    'used'          => $used_ids ?? [],
                    'used_data'     => $used,
                    'value'         => $ticket->fields['_cartridgeitem_id'] ?? null,
                    'no_label'      => true,
                    'options'       => [
                        'no_label' => true,
                    ],
                    'script' => '<script>
                            setTimeout(function() {                    
                                addRecurrentCheck(function() {
                                    if ($("[name=\\"_plugin_iservice_cartridge[cartridgeitems_id]\"]").val() != 0) {
                                        $("[name=\\"add_cartridge\\"]").closest("form").attr("action", window.location.href).submit();
                                        return true;
                                    }
                                    return false;
                                });}, 1000);
                          </script>'
                ];

                $data['addItemsSection']['inputs']['addButton'] = [
                    'order' => 2,
                    'type'  => 'button',
                    'name'  => 'add_cartridge',
                    'class' => 'submit',
                    'value' => empty($used_ids) ? __('Select', 'iservice') : _sx('button', 'Add'),
                    'options' => [
                        'on_click' => '$(this).closest("form").attr("action", window.location.href).submit();',
                    ],
                ];
            }
        }

        if (count($cartridges) < 1) {
            return $data;
        }

        $data['tableSection'] = [
            'type' => 'table',
            'header' => [
                'checkbox' => [
                    'hidden' => !$canEdit,
                    'value' => '',
                ],
                'name' => [
                    'value' => __('Name'),
                ],
                'type' => [
                    'value' => __('Type'),
                ],
                'empties' => [
                    'value' => __('Empties', 'iservice'),
                ],
            ],
        ];

        foreach ($cartridges as $key => $cartridge) {
            $data['tableSection']['rows'][$key] = [
                'cols' => [
                    'checkbox' => [
                        'hidden' => !$canEdit,
                        'input' => [
                            'type' => 'checkbox',
                            'name' => "_plugin_iservice_cartridges_tickets[$cartridge[IDD]]",
                            'disabled' => !$canEdit,
                            'zero_on_empty' => false,
                            'options' => [
                                'no_label' => true,
                            ],
                        ],
                    ],
                    'name' => [
                        'value' => "$cartridge[id] - $cartridge[name] ($cartridge[location_completename])"
                            . (!empty($cartridge['date_use']) ? " ". __('intalled on', 'iservice') . " $cartridge[date_use]" : '')
                            . (!empty($cartridge['date_out']) ? " " . __('emptied', 'iservice') . " $cartridge[date_out]" : ''),
                    ],
                    'mercurycode' => [
                        'hidden' => true,
                        'input' => [
                            'type' => 'hidden',
                            'name' => "_plugin_iservice_cartridge_mercurycodes[$cartridge[IDD]]",
                            'value' => $cartridge['mercurycode'],
                            'options' => [
                                'no_label' => true,
                            ],
                        ],
                    ],
                ],
            ];

            $supported_types = explode(',', $cartridge['supportedtypes']);
            if (empty($used[$cartridge['cid']]['types'])) {
                $used[$cartridge['cid']]['types'] = [];
            }

            foreach ($used[$cartridge['cid']]['types'] as $used_type_id) {
                if ($used_type_id != $cartridge['selected_type_id'] && false !== ($unset_index = array_search($used_type_id, $supported_types))) {
                    unset($supported_types[$unset_index]);
                }
            }

            if (empty($cartridge['selected_type_id'])) {
                $cartridge['selected_type_id'] = reset($supported_types);
            }

            if (count($supported_types) > 1 && (empty($used[$cartridge['cid']]['last']) || $cartridge['id'] === $used[$cartridge['cid']]['last'])) {
                $data['tableSection']['rows'][$key]['cols']['cartridgeTypeIds'] = [
                    'input' => [
                        'itemType' => 'PluginFieldsCartridgeitemtypeDropdown',
                        'type' => 'dropdown',
                        'name' => "_plugin_iservice_cartridge_type_ids[$cartridge[IDD]]",
                        'value' => $cartridge['selected_type_id'],
                        'options' => [
                            'condition' => ['id in (' . implode(',', $supported_types) . ')'],
                            'on_click' => '$(this).closest("form").attr("action", window.location.href).submit();',
                        ],
                    ],
                ];
            } else {
                $type_dropdown = new PluginFieldsCartridgeitemtypeDropdown();
                if ($type_dropdown->getFromDB($cartridge['selected_type_id'])) {
                    $data['tableSection']['rows'][$key]['cols']['cartridgeTypeIds'] = [
                        'input' => [
                            'type'  => 'hidden',
                            'name'  => "_plugin_iservice_cartridge_type_ids[$cartridge[IDD]]",
                            'value' => $cartridge['selected_type_id'],
                        ],
                        'value' => $type_dropdown->fields['name']
                    ];
                }
            }

            $cartridgeData = [
                'mercury_code_field' => $cartridge['mercurycode'],
                'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridge['selected_type_id'],
                'printers_id' => $cartridge['pid'],
            ];

            $emptyablesByCartridgeDropdownSettings = [
                'comments' => false,
                'name' => "_plugin_iservice_emptied_cartridge_ids[$cartridge[IDD]]",
                'value' => $cartridge['cartridges_id_emptied'],
                'readonly' => $readonly,
                'on_change' => 'cartridgesChanged = true;',
            ];

            $emptyablesByCartridgeDropdownSettings['elementsArray'] = $emptyableCartridges = PluginIserviceCartridge::getEmptyablesByCartridgeDropdownElementsArray($cartridgeData, $emptyablesByCartridgeDropdownSettings);

            if (empty($emptyableCartridges)) {
                $emptyablesByCartridgeDropdownSettings['type']                = 'hidden';
                $emptyablesByCartridgeDropdownSettings['value']               = 0;
                $emptyablesByCartridgeDropdownSettings['options']['no_label'] = true;
                $data['tableSection']['rows'][$key]['cols']['empties']        = [
                    'input' => $emptyablesByCartridgeDropdownSettings,
                    'value' => __('No cartridges to replace', 'iservice'),
                ];
            } elseif (count($emptyableCartridges) === 1) {
                $emptyablesByCartridgeDropdownSettings['type']                = 'hidden';
                $emptyablesByCartridgeDropdownSettings['value']               = array_keys($emptyableCartridges)[0];
                $emptyablesByCartridgeDropdownSettings['options']['no_label'] = true;
                $data['tableSection']['rows'][$key]['cols']['empties']        = [
                    'input' => $emptyablesByCartridgeDropdownSettings,
                    'value' => str_replace(") [", ")<br>[", $emptyableCartridges[array_keys($emptyableCartridges)[0]]),
                ];
            } else {
                $emptyablesByCartridgeDropdownSettings['type'] = 'dropdownArray';
            }
        }

        $data['tableSection']['buttons'] = [
            'updateButton' => [
                'input' => [
                    'type' => 'button',
                    'name' => 'update_cartridge',
                    'onclick' => 'cartridgesChanged=false;',
                    'value' => __('Update'),
                    'label' => __('Update'),
                    'options' => [
                        'buttonClass' => 'btn-outline-warning m-2',
                        'buttonIconClass' => 'ti ti-trash',
                        'on_click' => '$(this).closest("form").attr("action", window.location.href).submit();',
                    ],
                ],
            ],
            'removeButton' => [
                'input' => [
                    'type' => 'button',
                    'name' => 'remove_cartridge',
                    'onclick' => 'cartridgesChanged=false;',
                    'value' => __('Delete'),
                    'label' => __('Delete'),
                    'options' => [
                        'buttonClass' => 'btn-primary m-2',
                        'buttonIconClass' => 'far fa-save',
                        'on_click' => '$(this).closest("form").attr("action", window.location.href).submit();',
                    ],
                ],
            ],
        ];

        return $data;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {

        if (!$withtemplate) {
            $nb = 0;
            switch ($item->getType()) {
            case 'Ticket' :
                if (($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0) && (count($_SESSION["glpiactiveprofile"]["helpdesk_item_type"]) > 0)) {
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = countElementsInTable($this->getTable(), self::$items_id_1 . " = '" . $item->getID() . "'");
                    }

                    return self::createTabEntry(_n('Cartridge', 'Cartridges', Session::getPluralNumber()), $nb);
                }
            }
        }

        return '';
    }

    /**
     * Form for Followup on Massive action
     * */
    public static function showFormMassiveAction($ma): void
    {
        global $CFG_GLPI;

        switch ($ma->getAction()) {
        case 'add_item' :
            Dropdown::showAllItems("items_id", 0, 0, $_SESSION['glpiactive_entity'], $CFG_GLPI["ticket_types"], false, true, 'item_itemtype');
            echo "<br><input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            break;

        case 'delete_item' :
            Dropdown::showAllItems("items_id", 0, 0, $_SESSION['glpiactive_entity'], $CFG_GLPI["ticket_types"], false, true, 'item_itemtype');
            echo "<br><input type='submit' name='delete' value=\"" . __('Delete permanently') . "\" class='submit'>";
            break;
        }
    }

    /*
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     * */
    public static function showMassiveActionsSubForm(MassiveAction $ma): bool
    {

        switch ($ma->getAction()) {
        case 'add_item' :
            static::showFormMassiveAction($ma);
            return true;

        case 'delete_item' :
            static::showFormMassiveAction($ma);
            return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /*
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void
    {

        switch ($ma->getAction()) {
        case 'add_item' :
            $input = $ma->getInput();

            $cartridge_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $input[self::$items_id_1] = $id;

                    if ($cartridge_ticket->can(-1, CREATE, $input)) {
                        $ok = true;
                        if (!$cartridge_ticket->add($input)) {
                            $ok = false;
                        }

                        if ($ok) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                } else {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                }
            }
            return;

        case 'delete_item' :
            $input            = $ma->getInput();
            $cartridge_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $item_found = $cartridge_ticket->find(self::$items_id_1 . " = $id AND " . self::$items_id_2 . " = " . $input[self::$items_id_2]);
                    if (!empty($item_found)) {
                        $item_founds_id = array_keys($item_found);
                        $input['id']    = $item_founds_id[0];

                        if ($cartridge_ticket->can($input['id'], DELETE, $input)) {
                            $ok = true;
                            if (!$cartridge_ticket->delete($input)) {
                                $ok = false;
                            }

                            if ($ok) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                } else {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                }
            }
            return;
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /*
     * @since version 0.84
     *
     * @param $field
     * @param $values
     * @param $options   array
     * */
    public static function getSpecificValueToDisplay($field, $values, array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
        case self::$items_id_2:
            $itemtype2 = new self::$itemtype_2();
            return Dropdown::getDropdownName($itemtype2->getTable(), $values[$field]);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /*
     * @since version 0.84
     *
     * @param $field
     * @param $name            (default '')
     * @param $values          (default '')
     * @param $options   array
     *
     * @return string
     * */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $options['display'] = false;
        switch ($field) {
        case self::$items_id_2:
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return Dropdown::show(self::$itemtype_2, $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Add a message on add action
     * */
    public function addMessageOnAddAction(): void
    {
        $addMessAfterRedirect = false;
        if (isset($this->input['_add'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = new self::$itemtype_2;
            $item->getFromDB($this->fields[self::$items_id_2]);

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }

            if (($name = $item->getName()) == NOT_AVAILABLE) {
                // TRANS: %1$s is the itemtype, %2$d is the id of the item.
                $item->fields['name'] = sprintf(__('Cartridge - ID %2$d'), $item->getID());
            }

            $display = (isset($this->input['_no_message_link']) ? $item->getNameID() : $item->getLink());

            // Do not display quotes
            // TRANS : %s is the description of the added item.
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Cartridge successfully added'), stripslashes($display)));
        }
    }

    /**
     * Add a message on delete action
     * */
    public function addMessageOnPurgeAction(): void
    {

        if (!$this->maybeDeleted()) {
            return;
        }

        $addMessAfterRedirect = false;
        if (isset($this->input['_delete'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = new self::$itemtype_2;
            $item->getFromDB($this->fields[self::$items_id_2]);

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }

            if (isset($this->input['_no_message_link'])) {
                $display = $item->getNameID();
            } else {
                $display = $item->getLink();
            }

            // TRANS : %s is the description of the updated item.
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Consumable successfully deleted'), $display));
        }
    }

    public static function getForTicketId($ticket_id, $limit = null): array
    {
        $cartridge_ticket = new self();
        return $cartridge_ticket->find("tickets_id = $ticket_id", [], $limit);
    }

    public static function installWithType($ticketId, $cartridgeId, $typeId, $emptiedCartridgeId, $printerId, $supplierId, $locationId, $total2Black, $total2Color, $installTime): string|int
    {
        $cartridge                = new PluginIserviceCartridge();
        $cartridgeitemCustomField = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if (!$cartridge->getFromDB($cartridgeId) || !PluginIserviceDB::populateByItemsId($cartridgeitemCustomField, $cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridgeId to install it.";
        }

        if (!empty($emptiedCartridgeId)) {
            $oldCartridge = new PluginIserviceCartridge();
            if (!$oldCartridge->getFromDB($emptiedCartridgeId)) {
                return "Could not find cartridge with id $emptiedCartridgeId to uninstall it.";
            }

            // if ($oldCartridge->fields['printers_id'] != $printerId) {
            // return "Printer id mismatch. Old cartridge printer id: {$oldCartridge->fields['printers_id']}, new cartridge printer id: $printerId";
            // }
            if (!$oldCartridge->update(
                [
                    '_no_message' => true,
                    $oldCartridge->getIndexName() => $emptiedCartridgeId,
                    'date_out' => $installTime,
                    'tickets_id_out_field' => $ticketId,
                    'pages_out_field' => $total2Black,
                    'pages_color_out_field' => $total2Color,
                // 'printed_pages_field' => $oldCartridge->fields['printed_pages_field'] + $total2Black - $oldCartridge->fields['pages_use_field'],
                // 'printed_pages_color_field' => $oldCartridge->fields['printed_pages_color_field'] + $total2Color - $oldCartridge->fields['pages_color_use_field'],
                ]
            )
            ) {
                return "Could not update old cartridge with id $emptiedCartridgeId";
            }

            if (!empty($GLOBALS['ECHO_CARTRIDGE_INSTALL_INFO'])) {
                echo " Changed cartridge $emptiedCartridgeId. Total2_black: $total2Black, total2_color: $total2Color. Printed pages: {$oldCartridge->fields['printed_pages_field']}, printed color pages: {$oldCartridge->fields['printed_pages_color_field']}";
            }

            $uninstalled_multiplier = 1;
        } else {
            $uninstalled_multiplier = -1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'plugin_fields_cartridgeitemtypedropdowns_id' => $typeId,
                'printers_id' => $printerId,
                'suppliers_id_field' => $supplierId,
                'locations_id_field' => empty($locationId) ? '0' : $locationId,
                'date_use' => $installTime,
                'tickets_id_use_field' => $ticketId,
                'date_out' => 'NULL',
                'pages_use_field' => $total2Black,
                'pages_color_use_field' => $total2Color,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridgeId";
        }

        return $uninstalled_multiplier * $cartridge->getID();
    }

    public static function uninstallWithType($ticket_id, $cartridge_id, $type_id, $emptied_cartridge_id, $printer_id, $supplier_id, $location_id, $total2_black, $total2_color, $install_time): string|int
    {
        $cartridge                  = new PluginIserviceCartridge();
        $cartridgeitem_custom_field = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if (!$cartridge->getFromDB($cartridge_id) || !PluginIserviceDB::populateByItemsId($cartridgeitem_custom_field, $cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridge_id to uninstall it.";
        }

        $old_cartridge          = new PluginIserviceCartridge();
        $uninstalled_multiplier = 0;
        if (empty($emptied_cartridge_id)) {
            if (!PluginIserviceDB::populateByQuery(
                $old_cartridge, "join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = `glpi_cartridges`.cartridgeitems_id and cfci.itemtype = 'CartridgeItem' 
            join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = id and cfc.itemtype = 'Cartridge'
            where cfc.tickets_id_out_field = $ticket_id and cfc.mercury_code_field in ({$cartridgeitem_custom_field->fields['compatible_mercury_codes_field']}) and `glpi_plugin_fields_cartridgecartridgecustomfields`.plugin_fields_cartridgeitemtypedropdowns_id = {$cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']} limit 1"
            )
            ) {
                $uninstalled_multiplier = -1;
            }
        } else {
            if (!$old_cartridge->getFromDB($emptied_cartridge_id)) {
                return "Could not find cartridge with id $emptied_cartridge_id to reinstall it.";
            }
        }

        if ($uninstalled_multiplier === 0) {
            $old_cartridge->update(
                [
                    '_no_message' => true,
                    $old_cartridge->getIndexName() => $old_cartridge->getID(),
                    'date_out' => 'NULL',
                    'tickets_id_out_field' => 0,
                    'pages_out_field' => 0,
                    'pages_color_out_field' => 0,
                // 'printed_pages_field' => $old_cartridge->fields['printed_pages_field'] + $old_cartridge->fields['pages_use_field'] - $cartridge->fields['pages_use_field'],
                // 'printed_pages_color_field' => $old_cartridge->fields['printed_pages_color_field'] + $old_cartridge->fields['pages_color_use_field'] - $cartridge->fields['pages_color_use_field'],
                ]
            );
            $installed_multiplier = 1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'suppliers_id_field' => $supplier_id,
                'date_use' => 'NULL',
                'tickets_id_use_field' => 0,
                'date_out' => 'NULL',
                'pages_use_field' => 0,
                'pages_color_use_field' => 0,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridge_id";
        }

        return $installed_multiplier * $cartridge->getID();
    }

    // public static function getCartridgeData($cartridgeId): array
    // {
    // $query =
    // "SELECT
    // ct.id cartridge_ticket_id
    // , ct.plugin_fields_cartridgeitemtypedropdowns_id cartridge_item_type_id
    // , c.id cartridge_id
    // , ci.mercury_code_field mercury_code
    // , ci.compatible_mercury_codes_field compatible_mercury_codes
    // , ci.supported_types_field supported_types
    // FROM glpi_plugin_iservice_cartridges_tickets ct
    // INNER JOIN glpi_plugin_iservice_cartridges c ON c.id = ct.cartridges_id
    // INNER JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
    // WHERE ct.cartridges_id = $cartridgeId LIMIT 1";
    // global $DB;
    // $result = $DB->query($query);
    //
    // return $DB->fetchAssoc($result);
    // }
    //
    // public static function areCartridgesCompatible($newCartridgeId, $oldCartridgeId): bool
    // {
    // $newCartridge = self::getCartridgeData($newCartridgeId);
    // $oldCartridge = self::getCartridgeData($oldCartridgeId);
    //
    // if (empty($newCartridge) || empty($oldCartridge)) {
    // return false;
    // }
    //
    // if ($newCartridge['cartridge_item_type_id'] != $oldCartridge['cartridge_item_type_id']) {
    // return false;
    // }
    //
    // $oldCartridgeMercuryCodes = explode(',', str_replace('', '\'', $newCartridge['compatible_mercury_codes']));
    //
    // if ($newCartridge['mercury_code'] != $oldCartridge['mercury_code']
    // || !in_array($newCartridge['mercury_code'], $oldCartridgeMercuryCodes)
    // ) {
    // return false;
    // }
    //
    // if (empty(array_intersect(explode(',', $newCartridge['supported_types']), explode(',', $oldCartridge['supported_types'])))) {
    // return false;
    // }
    //
    // return true;
    // }
}
