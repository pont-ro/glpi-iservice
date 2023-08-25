<?php
return [
    'various' => [
        'icon' => 'ti ti-settings',
        'title' => __('Various data', 'iservice'),
        'items' => [
            'profile' => [
                'itemtype' => 'Profile',
                'label' => _n('Profile', 'Profiles', Session::getPluralNumber()),
            ],
            'group' => [
                'itemtype' => 'Group',
                'label' => _n('Group', 'Groups', Session::getPluralNumber()),
            ],
            'location' => [
                'itemtype' => 'Location',
                'label' => _n('Location', 'Locations', Session::getPluralNumber()),
            ],
            'user' => [
                'itemtype' => 'User',
                'label' => _n('User', 'Users', Session::getPluralNumber()),
            ],
            'userEmail' => [
                'itemtype' => 'UserEmail',
                'label' => _n('User Email', 'User Emails', Session::getPluralNumber()),
            ],
            'profileUser' => [
                'itemtype' => 'Profile_User',
                'label' => _n('Profile User', 'Profiles Users', Session::getPluralNumber()),
            ],
            'reminder' => [
                'itemtype' => 'Reminder',
                'label' => _n('Reminder', 'Reminders', Session::getPluralNumber()),
            ],
            'entityReminder' => [
                'itemtype' => 'Entity_Reminder',
                'label' => _n('Entity Reminder', 'Entity Reminders', Session::getPluralNumber()),
            ],
            'contact' => [
                'itemtype' => 'Contact',
                'label' => _n('Contact', 'Contacts', Session::getPluralNumber()),
            ],
            'pluginFieldsCartridgeItemTypeDropdown' => [
                'itemtype' => 'PluginFieldsCartridgeitemtypeDropdown',
                'label' => _n('Plugin Fields Cartridge Item type Dropdown', 'Plugin Fields Cartridge Items types Dropdown', Session::getPluralNumber()),
            ],
        ]
    ],
    'suppliers' => [
        'icon' => 'ti ti-building-skyscraper',
        'title' => _n('Supplier', 'Suppliers', Session::getPluralNumber()),
        'items' => [
            'supplier_types' => [
                'itemtype' => 'SupplierType',
                'label' => _n('Supplier type', 'Supplier types', Session::getPluralNumber()),
            ],
            'supplier' => [
                'itemtype' => 'Supplier',
                'label' => _n('Supplier', 'Suppliers', Session::getPluralNumber()),
            ],
            'supplier_customfields' => [
                'itemtype' => 'PluginFieldsSuppliersuppliercustomfield',
                'label' => _n('Supplier Custom Field', 'Supplier Custom Fields', Session::getPluralNumber(), 'iservice'),
            ],
            'contactSupplier' => [
                'itemtype' => 'Contact_Supplier',
                'label' => _n('Contact Supplier', 'Contact Suppliers', Session::getPluralNumber()),
            ],
        ]
    ],
    'printers' => [
        'icon' => 'ti ti-printer',
        'title' => _n('Printer', 'Printers', Session::getPluralNumber()),
        'items' => [
            'printertype' => [
                'itemtype' => 'PrinterType',
                'label' => _n('Printer type', 'Printer types', Session::getPluralNumber()),
            ],
            'printermodel' => [
                'itemtype' => 'PrinterModel',
                'label' => _n('Printer model', 'Printer models', Session::getPluralNumber()),
            ],
            'printerModelCustomField' => [
                'itemtype' => 'PluginFieldsPrintermodelprintermodelcustomfield',
                'label' => _n('Printer Model Custom Field', 'Printer Model Custom Fields', Session::getPluralNumber()),
            ],
            'manufacturer' => [
                'itemtype' => 'Manufacturer',
                'label' => _n('Manufacturer', 'Manufacturers', Session::getPluralNumber()),
            ],
            'state' => [
                'itemtype' => 'State',
                'label' => _n('State', 'States', Session::getPluralNumber()),
            ],
            'printer' => [
                'itemtype' => 'Printer',
                'label' => _n('Printer', 'Printers', Session::getPluralNumber()),
            ],
            'printerCustomField' => [
                'itemtype' => 'PluginFieldsPrinterprintercustomfield',
                'label' => _n('Printer Custom Field', 'Printer Custom Fields', Session::getPluralNumber()),
            ],
        ]
    ],
    'contracts' => [
        'icon' => 'ti ti-file',
        'title' => _n('Contract', 'Contracts', Session::getPluralNumber()),
        'items' => [
            'contracttypes' => [
                'itemtype' => 'ContractType',
                'label' => _n('Contract type', 'Contract types', Session::getPluralNumber()),
            ],
            'contract' => [
                'itemtype' => 'Contract',
                'label' => _n('Contract', 'Contracts', Session::getPluralNumber()),
            ],
            'contractCustomField' => [
                'itemtype' => 'PluginFieldsContractcontractcustomfield',
                'label' => _n('Contract Custom Field', 'Contract Custom Fields', Session::getPluralNumber()),
            ],
            'contractSupplier' => [
                'itemtype' => 'Contract_Supplier',
                'label' => _n('Contract Supplier', 'Contract Suppliers', Session::getPluralNumber()),
            ],
            'contractItem' => [
                'itemtype' => 'Contract_Item',
                'label' => _n('Contract Item', 'Contract Items', Session::getPluralNumber()),
            ],
        ]
    ],
    'tickets' => [
        'icon' => 'ti ti-ticket',
        'title' => _n('Ticket', 'Tickets', Session::getPluralNumber()),
        'items' => [
            'ticketTemplate' => [
                'itemtype' => 'TicketTemplate',
                'label' => _n('Ticket Template', 'Ticket Templates', Session::getPluralNumber()),
            ],
            'itilcategories' => [
                'itemtype' => 'ITILCategory',
                'label' => _n('ITIL Category', 'ITIL Categories', Session::getPluralNumber()),
            ],
            'tickets' => [
                'itemtype' => 'Ticket',
                'label' => _n('Ticket', 'Tickets', Session::getPluralNumber()),
            ],
            'ticketUser' => [
                'itemtype' => 'Ticket_User',
                'label' => _n('Ticket User', 'Ticket Users', Session::getPluralNumber()),
            ],
            'supplierTicket' => [
                'itemtype' => 'Supplier_Ticket',
                'label' => _n('Supplier Ticket', 'Supplier Tickets', Session::getPluralNumber()),
            ],
            'groupTicket' => [
                'itemtype' => 'Group_Ticket',
                'label' => _n('Group Ticket', 'Group Tickets', Session::getPluralNumber()),
            ],
            'itemTicket' => [
                'itemtype' => 'Item_Ticket',
                'label' => _n('Item Ticket', 'Item Tickets', Session::getPluralNumber()),
            ],
            'movements' => [
                'itemtype' => 'PluginIserviceMovement',
                'label' => _n('Movement', 'Movements', Session::getPluralNumber()),
            ],
            'eMEmail' => [
                'itemtype' => 'PluginIserviceEMEmail',
                'label' => _n('EMEmail', 'EMEmails', Session::getPluralNumber()),
            ],
            'ticketCustomField' => [
                'itemtype' => 'PluginFieldsTicketticketcustomfield',
                'label' => _n('Ticket Custom Field', 'Ticket Custom Fields', Session::getPluralNumber()),
            ],
            'itilFollowup' => [
                'itemtype' => 'ITILFollowup',
                'label' => _n('ITIL Followup', 'ITIL Followups', Session::getPluralNumber()),
            ],
        ]
    ],
    'cartridges' => [
        'icon' => 'ti ti-ink-pen',
        'title' => _n('Cartridge', 'Cartridges', Session::getPluralNumber()),
        'items' => [
            'cartridgeitemtype' => [
                'itemtype' => 'CartridgeItemType',
                'label' => _n('Cartridge Item type', 'Cartridge Items types', Session::getPluralNumber()),
            ],
            'cartridgeitem' => [
                'itemtype' => 'CartridgeItem',
                'label' => _n('Cartridge Item', 'Cartridge Items', Session::getPluralNumber()),
            ],
            'cartridgeItemCustomField' => [
                'itemtype' => 'PluginFieldsCartridgeitemcartridgeitemcustomfield',
                'label' => _n('Cartridge Item CCustom Field', 'Cartridge Item Custom Fields', Session::getPluralNumber()),
            ],
            'cartridge' => [
                'itemtype' => 'Cartridge',
                'label' => _n('Cartridge', 'Cartridges', Session::getPluralNumber()),
            ],
            'cartridgeCustomField' => [
                'itemtype' => 'PluginFieldsCartridgecartridgecustomfield',
                'label' => _n('Cartridge Custom Field', 'Cartridge Custom Fields', Session::getPluralNumber()),
            ],
            'cartridgeItemPrinterModel' => [
                'itemtype' => 'CartridgeItem_PrinterModel',
                'label' => _n('Cartridge Item Printer Model', 'Cartridge Items Printer Models', Session::getPluralNumber()),
            ],
            'consumableTicket' => [
                'itemtype' => 'PluginIserviceConsumable_Ticket',
                'label' => _n('Consumable Ticket', 'Consumable Tickets', Session::getPluralNumber()),
            ],
        ]
    ],
    'orders' => [
        'icon' => 'ti ti-truck-delivery',
        'title' => _n('Order', 'Orders', Session::getPluralNumber()),
        'items' => [
            'orderStatuses' => [
                'itemtype' => 'PluginIserviceOrderStatus',
                'label' => _n('Order Status', 'Order Statuses', Session::getPluralNumber()),
            ],
            'extOrders' => [
                'itemtype' => 'PluginIserviceExtOrder',
                'label' => _n('External Order', 'External Orders', Session::getPluralNumber()),
            ],
            'intOrder' => [
                'itemtype' => 'PluginIserviceIntOrder',
                'label' => _n('Internal Order', 'Internal Orders', Session::getPluralNumber()),
            ],
            'intOrderExtOrder' => [
                'itemtype' => 'PluginIserviceIntOrder_ExtOrder',
                'label' => _n('Internal-External Order', 'Internal-External Orders', Session::getPluralNumber()),
            ],
        ]
    ],
    'other' => [
        'icon' => 'ti ti-files',
        'title' => _n('Other', 'Others', Session::getPluralNumber()),
        'items' => [
            'infocom' => [
                'itemtype' => 'Infocom',
                'label' => _n('Infocom', 'Infocoms', Session::getPluralNumber()),
            ],
            'download' => [
                'itemtype' => 'PluginIserviceDownload',
                'label' => _n('Download', 'Downloads', Session::getPluralNumber()),
            ],
            'log' => [
                'itemtype' => 'Log',
                'label' => _n('Log', 'Logs', Session::getPluralNumber()),
            ],
        ],
    ],
];
