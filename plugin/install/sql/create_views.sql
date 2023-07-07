create or replace view hmarfa_total_facturi as
select
    `f`.`codbenef` as `codbenef`,
    count(`f`.`nrfac`) as `numar_facturi`,
    sum(`f`.`valinc` - `f`.`valpla`) as `total_facturi`
from `hmarfa_facturi` `f`
where `f`.`tip` like 'tf%' and `f`.`valinc` - `f`.`valpla` > 0
group by `f`.`codbenef`;

create or replace view glpi_plugin_iservice_printers as
select
    `p`.`id` as `id`,
    concat(coalesce(concat(`p`.`serial`,' '),''),'(',`p`.`name`,')', coalesce(concat(' - ',`l`.`completename`),'')) as `name`,
    `p`.`name` as `original_name`,
    `p`.`contact` as `contact`,
    `p`.`contact_num` as `contact_num`,
    `p`.`users_id_tech` as `users_id_tech`,
    `p`.`groups_id_tech` as `groups_id_tech`,
    `p`.`serial` as `serial`,
    `p`.`otherserial` as `otherserial`,
    `p`.`comment` as `comment`,
    `p`.`memory_size` as `memory_size`,
    `p`.`locations_id` as `locations_id`,
    `p`.`printertypes_id` as `printertypes_id`,
    `p`.`printermodels_id` as `printermodels_id`,
    `p`.`manufacturers_id` as `manufacturers_id`,
    `p`.`is_deleted` as `is_deleted`,
    `p`.`init_pages_counter` as `init_pages_counter`,
    `p`.`last_pages_counter` as `last_pages_counter`,
#     `p`.`initial_color_pages` as `initial_color_pages`, todo: confirm this with hupu
    `p`.`users_id` as `users_id`,
    `p`.`groups_id` as `groups_id`,
    `p`.`states_id` as `states_id`,
    `s`.`id` as `supplier_id`,
    `s`.`name` as `supplier_name`
from (((`glpi_printers` `p`
    left join `glpi_infocoms` `i` on(`i`.`items_id` = `p`.`id` and `i`.`itemtype` = 'printer'))
    left join `glpi_suppliers` `s` on(`s`.`id` = `i`.`suppliers_id`))
    left join `glpi_locations` `l` on(`l`.`id` = `p`.`locations_id`));

create or replace view glpi_plugin_iservice_printers_last_tickets as
select
    distinct it.items_id printers_id,
    first_value(t.id) over w tickets_id,
    first_value(t.`status`) over w `status`,
    first_value(t.data_luc) over w data_luc,
    first_value(t.total2_black) over w total2_black,
    first_value(t.total2_color) over w total2_color
from glpi_items_tickets it
    join glpi_printers p on p.id = it.items_id
    join glpi_tickets t on t.id = it.tickets_id and t.is_deleted = 0
where it.itemtype = 'printer'
window w as (partition by it.items_id order by t.data_luc desc, t.id desc);

create or replace view glpi_plugin_iservice_consumable_compatible_printers_counts as
select c.id, count(distinct (p.id)) `count`, group_concat(concat(p.name, ' (', p.id, ')') separator '\\n') as pids
from glpi_cartridges c
         left join glpi_locations l1 on l1.id = c.fk_location
         left join glpi_plugin_fields_suppliersuppliercustomfields cfs on cfs.items_id = c.fk_enterprise and cfs.itemtype = 'supplier'
         join glpi_cartridgeitems_printermodels cp on cp.cartridgeitems_id = c.cartridgeitems_id
         join glpi_printers p on p.printermodels_id = cp.printermodels_id
         left join glpi_locations l2 on l2.id = p.locations_id
         join glpi_infocoms ic on ic.itemtype = 'printer'
    and ic.items_id = p.id
    and find_in_set (ic.suppliers_id, cfs.group_field)
where p.is_deleted = 0
  and (c.fk_location = p.locations_id or coalesce(l1.locations_id, 0) = coalesce(l2.locations_id, 0))
group by c.id;

create or replace view glpi_plugin_iservice_consumables as
select
    codmat id,
    concat(codmat, ' - ', n.denum, ': ', sum(stoci-iesiri), case when ci.name is null then '' else ' [cm]' end) name,
    concat(codmat, ' - ', n.denum) name_with_id,
    ci.name cartridgeitem_name,
    n.denum denumire,
    n.grupa,
    sum(stoci-iesiri) stoc
from hmarfa_lotm l
         left join hmarfa_nommarfa n on n.cod = l.codmat
         left join glpi_cartridgeitems ci on ci.ref = l.codmat and ci.is_deleted = 0
         inner join hmarfa_tran t using (nrtran)
group by codmat;

create or replace view glpi_plugin_iservice_intorders_view as
select ic.*, concat(c.id, ' - ', c.denumire, ' (', ic.amount, ' buc.)') name
from glpi_plugin_iservice_intorders ic
left join glpi_plugin_iservice_consumables c on c.id = ic.plugin_iservice_consumables_id;

create or replace view glpi_plugin_iservice_printers_last_closed_tickets as
select
    distinct it.items_id printers_id,
    first_value(t.id) over w tickets_id,
    first_value(t.`status`) over w `status`,
    first_value(t.data_luc) over w data_luc,
    first_value(t.total2_black) over w total2_black,
    first_value(t.total2_color) over w total2_color
from glpi_items_tickets it
    join glpi_printers p on p.id = it.items_id
    join glpi_tickets t on t.id = it.tickets_id and t.`status` = 6 and t.is_deleted = 0
where it.itemtype = 'printer'
window w as (partition by it.items_id order by t.data_luc desc, t.id desc);

create or replace view glpi_plugin_iservice_printer_unclosed_ticket_counts as
select it.items_id printers_id, count(it.tickets_id) ticket_count
from glpi_items_tickets it
    join glpi_tickets t on t.id = it.tickets_id and not t.status = 6 and t.is_deleted = 0
where it.itemtype = 'printer'
group by it.items_id;
