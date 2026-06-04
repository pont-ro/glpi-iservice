<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id tid, it.items_id pid, s.id supplier_id, s.name supplier_name
        from glpi_tickets t
        join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer' and it.items_id > 0
        left join glpi_suppliers_tickets st on st.tickets_id = t.id
        left join glpi_infocoms ic on ic.items_id = it.items_id and ic.itemtype = 'Printer'
        left join glpi_suppliers s on s.id = ic.suppliers_id and s.is_deleted = 0
        where t.id > 55000 and st.suppliers_id is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with printer but without partner.',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with printer but without partner',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has printer <b>[pid]</b> but no partner.
                <a id='fix-ticket-supplier-[tid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIserviceTicket&operation=AssignSupplier&id=[tid]&supplier_id=[supplier_id]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-ticket-supplier-[tid]\").closest(\"li\").remove();}});'>»»» FIX «««</a>
                <span style='color:gray'>(This will add partner: <b>[supplier_id] - [supplier_name]</b>)</span>",
        ],
    ],
];
