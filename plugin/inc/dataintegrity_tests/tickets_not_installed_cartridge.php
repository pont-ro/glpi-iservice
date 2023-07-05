<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        SELECT tcf.items_id tid 
        FROM glpi_plugin_fields_ticketcustomfields tcf
        JOIN glpi_plugin_iservice_consumables_tickets ct on ct.tickets_id = tcf.items_id
        JOIN glpi_items_tickets it on it.tickets_id = ct.tickets_id and it.itemtype = 'Printer'
        JOIN glpi_plugin_fields_printercustomfields pcf on pcf.items_id = it.items_id and pcf.itemtype = 'Printer'
        JOIN glpi_suppliers_tickets st on st.tickets_id = it.tickets_id
        JOIN glpi_plugin_fields_suppliercustomfields scf on scf.items_id = st.suppliers_id and scf.itemtype = 'Supplier'
        WHERE tcf.itemtype = 'Ticket'
          AND tcf.deliveredfield = 1
          AND ct.create_cartridge = 1
          AND ct.amount > 0
          AND ct.new_cartridge_ids IS NULL
          AND pcf.emaintenancefield = 1
          AND scf.cartridge_management = 1
          AND ct.tickets_id NOT IN ('84693', '88333', '92634')
        GROUP BY tid;
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets which do not create cartridge but should'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets which do not create cartridge but should',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> does not create cartridge but it should",
        ],
    ],
];
