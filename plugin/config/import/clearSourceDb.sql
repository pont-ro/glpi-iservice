start transaction;

    create table if not exists glpi_infocoms_deleted_rows as select * from glpi_infocoms WHERE id = -1;

    insert into glpi_infocoms_deleted_rows
    select infc.*
    from glpi_infocoms infc
             left join glpi_cartridges c on infc.items_id = c.id
    where infc.itemtype = 'cartridge' and c.id is null;

    delete infc
    from glpi_infocoms infc
             left join glpi_cartridges c on infc.items_id = c.id
    where infc.itemtype = 'cartridge' and c.id is null;

commit;


start transaction;

    create table if not exists glpi_suppliers_tickets_deleted_rows as select * from glpi_suppliers_tickets WHERE id = -1;

    insert into glpi_suppliers_tickets_deleted_rows
    select spt.*
    from glpi_suppliers_tickets spt
             left join glpi_tickets t on spt.tickets_id = t.id
    where t.id is null;

    delete spt
    from glpi_suppliers_tickets spt
             left join glpi_tickets t on spt.tickets_id = t.id
    where t.id is null;

commit;

start transaction;

    create table if not exists glpi_tickets_users_deleted_rows as select * from glpi_tickets_users WHERE id = -1;

    insert into glpi_tickets_users_deleted_rows
    SELECT t1.*
    FROM glpi_tickets_users t1
             JOIN (
        SELECT tickets_id, users_id, type, IFNULL(alternative_email, '') AS normalized_email,
               MIN(id) AS min_id
        FROM glpi_tickets_users
        GROUP BY tickets_id, users_id, type, normalized_email
        HAVING COUNT(*) > 1
    ) AS duplicated
                  ON t1.tickets_id = duplicated.tickets_id
                      AND t1.users_id = duplicated.users_id
                      AND t1.type = duplicated.type
                      AND IFNULL(t1.alternative_email, '') = duplicated.normalized_email
                      AND t1.id <> duplicated.min_id;

    delete t1
    FROM glpi_tickets_users t1
             JOIN (
        SELECT tickets_id, users_id, type, IFNULL(alternative_email, '') AS normalized_email,
               MIN(id) AS min_id
        FROM glpi_tickets_users
        GROUP BY tickets_id, users_id, type, normalized_email
        HAVING COUNT(*) > 1
    ) AS duplicated
                  ON t1.tickets_id = duplicated.tickets_id
                      AND t1.users_id = duplicated.users_id
                      AND t1.type = duplicated.type
                      AND IFNULL(t1.alternative_email, '') = duplicated.normalized_email
                      AND t1.id <> duplicated.min_id;


    insert into glpi_tickets_users_deleted_rows
    select tu.*
    from glpi_tickets_users tu
             left join glpi_tickets t on tu.tickets_id = t.id
    where t.id IS NULL;

    delete tu
    from glpi_tickets_users tu
             left join glpi_tickets t on tu.tickets_id = t.id
    where t.id IS NULL;

    insert into glpi_tickets_users_deleted_rows
    select * FROM glpi_tickets_users where id in (452314, 452315);
    delete from glpi_tickets_users where id in (452314, 452315);

commit;

start transaction;

    create table if not exists glpi_items_tickets_deleted_rows as select * from glpi_items_tickets WHERE id = -1;

    insert into glpi_items_tickets_deleted_rows
    select it.*
    from glpi_items_tickets it
             left join glpi_tickets t on it.tickets_id = t.id
    where t.id IS NULL AND it.tickets_id != 0;

    delete it
    from glpi_items_tickets it
             left join glpi_tickets t on it.tickets_id = t.id
    where t.id IS NULL AND it.tickets_id != 0;

commit;

start transaction;

    create table if not exists cartridgeitemcartridgecustomfields_deleted_rows as
        select * from glpi_plugin_fields_cartridgeitemcartridgecustomfields WHERE id = -1;

    insert into cartridgeitemcartridgecustomfields_deleted_rows
    select cfci.*
    from glpi_plugin_fields_cartridgeitemcartridgecustomfields cfci
             left join glpi_cartridgeitems ci on cfci.items_id = ci.id
    where ci.id IS NULL;

    delete cfci
    from glpi_plugin_fields_cartridgeitemcartridgecustomfields cfci
             left join glpi_cartridgeitems ci on cfci.items_id = ci.id
    where ci.id IS NULL;

commit;

start transaction;

    create table if not exists glpi_cartridgeitems_printermodels_deleted_rows as
        select * from glpi_cartridgeitems_printermodels WHERE id = -1;

    insert into glpi_cartridgeitems_printermodels_deleted_rows
    select cipm.*
    from glpi_cartridgeitems_printermodels cipm
        left join glpi_printermodels pm on cipm.printermodels_id = pm.id
    where pm.id IS NULL;

    delete cipm
    from glpi_cartridgeitems_printermodels cipm
             left join glpi_printermodels pm on cipm.printermodels_id = pm.id
    where pm.id IS NULL;

commit;

start transaction;

    create table if not exists glpi_plugin_iservice_movements_deleted_rows as
        select * from glpi_plugin_iservice_movements WHERE id = -1;

    insert into glpi_plugin_iservice_movements_deleted_rows
    select m.*
    from glpi_plugin_iservice_movements m
             left join glpi_printers p on m.items_id = p.id
    where p.id IS NULL AND m.itemtype = 'Printer';

    delete m
    from glpi_plugin_iservice_movements m
             left join glpi_printers p on m.items_id = p.id
    where p.id IS NULL AND m.itemtype = 'Printer';

    /* from movements table items that don't have a valid supplier should NOT be deleted, but added 100000000 + ID */
    /*insert into table glpi_plugin_iservice_movements_deleted_rows
    select m.*
    from glpi_plugin_iservice_movements m
        left join glpi_suppliers s on m.suppliers_id = s.id
    where s.id IS NULL AND m.suppliers_id != 0;

    delete m
    from glpi_plugin_iservice_movements m
             left join glpi_suppliers s on m.suppliers_id = s.id
    where s.id IS NULL AND m.suppliers_id != 0;*/

commit;

start transaction;

    UPDATE glpi_plugin_fields_printermodelprintermodelcustomfields
    SET items_id = 295
    WHERE items_id = 715;

    create table if not exists glpi_printermodels_deleted_rows as select * from glpi_printermodels WHERE id = -1;

    insert into glpi_printermodels_deleted_rows
    select * from glpi_printermodels WHERE id IN (638, 715);

    delete from glpi_printermodels WHERE id IN (638, 715);

commit;

UPDATE glpi_suppliers_tickets SET alternative_email = '' WHERE alternative_email LIKE 'Array';

start transaction;

    create table if not exists glpi_plugin_fields_printercustomfields_deleted_rows as
        select * from glpi_plugin_fields_printercustomfields WHERE id = -1;

    insert into glpi_plugin_fields_printercustomfields_deleted_rows
    select cfp.*
    from glpi_plugin_fields_printercustomfields cfp
             left join glpi_printers p on cfp.items_id = p.id
    where p.id is null;

    delete cfp
    from glpi_plugin_fields_printercustomfields cfp
             left join glpi_printers p on cfp.items_id = p.id
    where p.id is null;

commit;

start transaction;

    create table if not exists glpi_plugin_fields_ticketcustomfields_deleted_rows as
        select * from glpi_plugin_fields_ticketcustomfields WHERE id = -1;

    insert into glpi_plugin_fields_ticketcustomfields_deleted_rows
    select cft.*
    from glpi_plugin_fields_ticketcustomfields cft
             left join glpi_tickets t on cft.items_id = t.id
    where t.id is null;

    delete cft
    from glpi_plugin_fields_ticketcustomfields cft
             left join glpi_tickets t on cft.items_id = t.id
    where t.id is null;

commit;

start transaction;

    create table if not exists glpi_plugin_iservice_consumables_tickets_deleted_rows as
        select * from glpi_plugin_iservice_consumables_tickets WHERE id = -1;

    insert into glpi_plugin_iservice_consumables_tickets_deleted_rows
    select ct.*
    from glpi_plugin_iservice_consumables_tickets ct
             left join glpi_tickets t on ct.tickets_id = t.id
    where t.id is null AND ct.tickets_id != 0;

    delete ct
    from glpi_plugin_iservice_consumables_tickets ct
             left join glpi_tickets t on ct.tickets_id = t.id
    where t.id is null AND ct.tickets_id != 0;

commit;
