<?php

// Imported from iService2, needs refactoring.
require "../inc/includes.php";

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$id       = IserviceToolBox::getInputVariable('id');
$add      = IserviceToolBox::getInputVariable('add');
$move     = IserviceToolBox::getInputVariable('move');
$update   = IserviceToolBox::getInputVariable('update');
$delete   = IserviceToolBox::getInputVariable('delete');
$itemtype = IserviceToolBox::getInputVariable('itemtype', 'Printer');

if (empty($id) && (empty($itemtype) || !class_exists("PluginIservice$itemtype"))) {
    PluginIserviceHtml::header(__('Move', 'iservice') . " " . __($itemtype, 'iservice'));
    Html::displayErrorAndDie(__('Invalid movement type', 'iservice') . ": $itemtype");
}

$movement = new PluginIserviceMovement($itemtype);

$post = filter_input_array(INPUT_POST);

if (!empty($move)) {
    $update = 'update';
}

if (!empty($add)) {
    $movement->check(-1, CREATE, $post);

    if (($id = $movement->add($post)) !== false) {
        // if ticket_id is set and positive, it means that we have to close the ticket.
        if (isset($post['ticket_id']) && $post['ticket_id'] > 0) {
            $ticket       = new PluginIserviceTicket();
            $ticket_input = [
                'id' => $post['ticket_id'],
                '_no_message' => true,
                'status' => Ticket::CLOSED,
            ];
            $ticket->update($ticket_input);
        }

        Html::redirect("movement.form.php?id=$id");
    } else {
        Html::displayErrorAndDie("Error adding movement");
    }
} elseif (!empty($update) && !empty($id)) {
    $movement->check($id, UPDATE);
    $movement->update($post) or die("Error updating movement");

    if (!empty($move)) {
        $item         = new $post['itemtype'];
        $input_fields = [
            'items_id' => 'id',
            'users_id' => 'users_id',
            'states_id' => 'states_id',
            'locations_id' => 'locations_id',
            'users_id_tech' => 'users_id_tech',
            'groups_id' => 'groups_id',
            'contact_num' => 'contact_num',
            'contact' => 'contact',
        ];
        foreach ($input_fields as $post_name => $field_name) {
            $input[$field_name] = $post[$post_name];
        }

        $item->check($post['items_id'], UPDATE);
        $item->update($input) or die("Error updating $post[itemtype]");
        switch ($post['itemtype']) {
        case 'Printer': $item_customfields = new PluginFieldsPrintercustomfield();

            break;
        case 'Supplier': $item_customfields = new PluginFieldsSuppliercustomfield();

            break;
        case 'Ticket': $item_customfields = new PluginFieldsTicketcustomfield();

            break;
        default: echo "Invalid itemtype";

            die;
        }

        $item_customfields->getFromDBByItemsId($post['items_id']);
        $item_customfields->update(
            [
                $item_customfields->getIndexName() => $item_customfields->getID(),
                'total2_black_fact' => $post['total2_black_fact'],
                'total2_color_fact' => $post['total2_color_fact'],
                'data_fact' => $post['data_fact'],
                'data_exp_fact' => $post['data_exp_fact'],
                'week_nr' => $post['week_number'],
                'usageaddressfield' => $post['usage_address'],
                'contact_num' => $post['contact_num'],
                'contact' => $post['contact'],
                'dailybkaveragefield' => $post['dba'],
                'dailycoloraveragefield' => empty($post['dca']) ? 'NULL' : $post['dca'],
                'disableemfield' => $post['disableem'],
                'snoozereadcheckfield' => $post['snoozereadcheck'],
            ]
        ) or die("Error updating $post[itemtype] customfields");
        $infocom = new Infocom();
        $infocom->getFromDBforDevice($post['itemtype'], $post['items_id']) or die("Error getting infocom to update partner of $post[itemtype]");
        $infocom->update([$infocom->getIndexName() => $infocom->getID(),'suppliers_id' => $post['suppliers_id']]) or die("Error updating $post[itemtype] with the new partner");
        $movement->update([$movement->getIndexName() => $id, 'moved' => 1]) or die("Error updating movement (moved=1)");

        // Toroljuk azokat a szerzodeseket a jelenlegi partnerrol, amelyek az adott gepet tartalmazzak,
        // de csak akkor ha a szerzodes nem tartalmaz ugyanehez a partnerhez tartozo masik gepet.
        $DB->query(
            "DELETE FROM glpi_contracts_suppliers
                                WHERE contracts_id IN (
                                            SELECT ci.contracts_id
                                            FROM glpi_contracts_items ci
                                            LEFT JOIN (SELECT * FROM glpi_contracts_suppliers) cs ON cs.contracts_id = ci.contracts_id
                                            WHERE itemtype = '$post[itemtype]'
                                                AND items_id = $post[items_id]
                                                AND NOT EXISTS (SELECT * FROM glpi_contracts_items ci2
                                                                                LEFT JOIN glpi_infocoms ic ON ic.itemtype = '$post[itemtype]' AND ic.items_id = ci2.items_id
                                                                                WHERE ci.contracts_id = ci2.contracts_id
                                                                                    AND NOT ci.items_id = ci2.items_id
                                                                                    AND ic.suppliers_id = cs.suppliers_id)
                                )"
        ) or die("Error deleting partner from old contract");
        // Leszedjuk a gepet a jelenlegi szerzodeserol. Ezen a ponton a szerzodes mar le van szedve a partnerrol ha szukseges.
        $DB->query("DELETE FROM glpi_contracts_items WHERE itemtype = '$post[itemtype]' AND items_id = $post[items_id]") or die("Error deleting item from old contract");

        if (!empty($post['contracts_id'])) {
            $contract_item = new Contract_Item();
            $contract_item->add(['add' => 'add','itemtype' => $post['itemtype'],'items_id' => $post['items_id'],'contracts_id' => $post['contracts_id']]) or die("Error adding item to new contract");
            $contract_supplier = new Contract_Supplier();
            // If supplier is already on contract, this will throw an error, so don't check for errors
            $contract_supplier->add(['add' => 'add','contracts_id' => $post['contracts_id'],'suppliers_id' => $post['suppliers_id']]);
        }

        Session::addMessageAfterRedirect(__("Movement completed successfully", "iservice"), true, INFO, true);

        if (!$post['ticket_out_exists'] && in_array($post['type'], [PluginIserviceMovement::TYPE_OUT, PluginIserviceMovement::TYPE_MOVE])) {
            $ticket = new PluginIserviceTicket();
            $ticket->prepareForShow(
                [
                    'mode' => PluginIserviceTicket::MODE_CREATEQUICK,
                    'get' => [
                        'items_id' => ['Printer' => [$post['items_id']]],
                        'locations_id' => $post['locations_id'],
                        '_suppliers_id_assign' => $post['suppliers_id'],
                        '_users_id_assign' => $post['users_id_tech'],
                        'itilcategories_id' => PluginIserviceTicket::getItilCategoryId('livrare echipament'),
                        'name' => 'livrare echipament',
                        'content' => 'livrare echipament',
                        'followup_content' => 'livrare echipament'
                    ]
                ]
            );
            foreach ($ticket->fields as $field_name => $field_value) {
                if (strpos($field_name, '[')) {
                    $parts                                               = explode('[', $field_name, 2);
                    $ticket->fields[$parts[0]][substr($parts[1], 0, -1)] = $field_value;
                }
            }

            $ticket->fields['items_id']             = ['Printer' => [$post['items_id']]];
            $ticket->fields['_suppliers_id_assign'] = $post['suppliers_id'];
            $ticket->fields['_movement2_id']        = $id;
            $ticket->fields['status']               = Ticket::WAITING;
            $ticket->fields['add']                  = 'add';
            $ticket->fields['mode']                 = PluginIserviceTicket::MODE_CREATEQUICK;
            $ticket->fields['_no_message']          = 1;
            $ticket_id                              = $ticket->add($ticket->fields);
            Html::redirect("ticket.form.php?id=$ticket_id&mode=" . PluginIserviceTicket::MODE_CLOSE . "&_redirect_on_success=" . urlencode("movement.form.php?id=$id"));
        }
    }
} elseif (!empty($delete)) {
    $movement->check($delete, DELETE);
    if ($movement->delete(['delete' => 'delete', 'id' => $delete, '_no_message' => true])) {
        Session::addMessageAfterRedirect('Mutare revocată', true);
        Html::redirect('view.php?view=movements');
    } else {
        Session::addMessageAfterRedirect('Could not delete movement', true, ERROR);
        Html::back();
    }
}

Session::checkRight('plugin_iservice_movement', UPDATE);

Html::header(__('Move', 'iservice') . " " . __($itemtype, 'iservice'));

$movement->ShowForm(null,[]);

Html::footer();