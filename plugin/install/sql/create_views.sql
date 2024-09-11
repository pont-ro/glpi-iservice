create or replace view hmarfa_total_facturi as
select
    `f`.`codbenef` as `codbenef`,
    count(`f`.`nrfac`) as `numar_facturi`,
    sum(`f`.`valinc` - `f`.`valpla`) as `total_facturi`
from `hmarfa_facturi` `f`
where `f`.`tip` like 'tf%' and `f`.`valinc` - `f`.`valpla` > 0
group by `f`.`codbenef`;

create or replace view glpi_plugin_iservice_tickets as
select
    t.id as id,
    t.entities_id as entities_id,
    t.name as name,
    t.date as date,
    t.closedate as closedate,
    t.solvedate as solvedate,
    t.takeintoaccountdate as takeintoaccountdate,
    t.date_mod as date_mod,
    t.users_id_lastupdater as users_id_lastupdater,
    t.status as status,
    t.users_id_recipient as users_id_recipient,
    t.requesttypes_id as requesttypes_id,
    t.content as content,
    t.urgency as urgency,
    t.impact as impact,
    t.priority as priority,
    t.itilcategories_id as itilcategories_id,
    t.type as type,
    t.global_validation as global_validation,
    t.slas_id_ttr as slas_id_ttr,
    t.slalevels_id_ttr as slalevels_id_ttr,
    t.time_to_resolve as time_to_resolve,
    t.time_to_own as time_to_own,
    t.begin_waiting_date as begin_waiting_date,
    t.sla_waiting_duration as sla_waiting_duration,
    t.ola_waiting_duration as ola_waiting_duration,
    t.olas_id_tto as olas_id_tto,
    t.olas_id_ttr as olas_id_ttr,
    t.olalevels_id_ttr as olalevels_id_ttr,
    t.ola_tto_begin_date as ola_tto_begin_date,
    t.ola_ttr_begin_date as ola_ttr_begin_date,
    t.internal_time_to_resolve as internal_time_to_resolve,
    t.internal_time_to_own as internal_time_to_own,
    t.waiting_duration as waiting_duration,
    t.close_delay_stat as close_delay_stat,
    t.solve_delay_stat as solve_delay_stat,
    t.takeintoaccount_delay_stat as takeintoaccount_delay_stat,
    t.actiontime as actiontime,
    t.is_deleted as is_deleted,
    t.locations_id as locations_id,
    t.validation_percent as validation_percent,
    t.date_creation as date_creation,
    cft.id as cfid,
    cft.plugin_fields_containers_id as plugin_fields_containers_id,
    cft.contact_name_field as contact_name_field,
    cft.contact_phone_field as contact_phone_field,
    cft.device_observations_field as device_observations_field,
    cft.movement_id_field as movement_id_field,
    cft.no_travel_field as no_travel_field,
    cft.movement2_id_field as movement2_id_field,
    cft.without_paper_field as without_paper_field,
    cft.plugin_fields_ticketexporttypedropdowns_id as plugin_fields_ticketexporttypedropdowns_id,
    cft.exported_field as exported_field,
    cft.em_mail_id_field as em_mail_id_field,
    cft.delivered_field as delivered_field,
    cft.consumable_prices_field as consumable_prices_field,
    cft.consumable_descriptions_field as consumable_descriptions_field,
    cft.cartridge_install_date_field as cartridge_install_date_field,
    cft.notification_mail_field as notification_mail_field,
    cft.total2_black_field as total2_black_field,
    cft.total2_color_field as total2_color_field,
    cft.effective_date_field as effective_date_field
from glpi_tickets t
     left join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = t.id and cft.itemtype = 'Ticket';

create or replace view glpi_plugin_iservice_printers_last_closed_tickets as
select
    lt.printers_id
     , lt.tickets_id
     , lt.status
     , cft.effective_date_field
     , cft.total2_black_field
     , cft.total2_color_field
from (
         select
             distinct it.items_id printers_id
                    , first_value(t.id) over w tickets_id
                    , first_value(t.status) over w status
         from glpi_items_tickets it
                  join glpi_tickets t on t.id = it.tickets_id and t.is_deleted = 0 and t.`status` = 6
                  join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = t.id and cft.itemtype = 'Ticket'
         where it.itemtype = 'Printer'
         window w as (partition by it.items_id order by cft.effective_date_field desc, t.id desc)
     ) lt
         join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = lt.tickets_id and cft.itemtype = 'Ticket';

create or replace view glpi_plugin_iservice_printers as
select
    `p`.`id` as `id`,
    concat(
            coalesce(concat(`p`.`serial`, ' '), ''),
            '(', `p`.`name`,')',
            coalesce(concat(' - ', `l`.`completename`), ''),
            case
                when `l`.`completename` = `cfp`.`usage_address_field` then ''
                else case
                         when coalesce(`cfp`.`usage_address_field`, '') = '' then ''
                         else concat(' | ', `cfp`.`usage_address_field`)
                     end
            end
    ) as `name`,
    concat(`p`.`name`, coalesce(concat(' - ',`l`.`completename`),'')) as `name_and_location`,
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
    `p`.`users_id` as `users_id`,
    `p`.`groups_id` as `groups_id`,
    `p`.`states_id` as `states_id`,
    `s`.`id` as `supplier_id`,
    `s`.`name` as `supplier_name`,
    `cfp`.`id` as `cfid`,
    `cfp`.`plugin_fields_containers_id` as `plugin_fields_containers_id`,
    `cfp`.`invoice_date_field` as `invoice_date_field`,
    `cfp`.`invoice_expiry_date_field` as `invoice_expiry_date_field`,
    `cfp`.`invoiced_total_black_field` as `invoiced_total_black_field`,
    `cfp`.`invoiced_total_color_field` as `invoiced_total_color_field`,
    `cfp`.`invoiced_value_field` as `invoiced_value_field`,
    `cfp`.`week_nr_field` as `week_nr_field`,
    `cfp`.`plan_observations_field` as `plan_observations_field`,
    `cfp`.`contact_gps_field` as `contact_gps_field`,
    `cfp`.`em_field` as `em_field`,
    `cfp`.`disable_em_field` as `disable_em_field`,
    `cfp`.`last_read_field` as `last_read_field`,
    `cfp`.`snooze_read_check_field` as `snooze_read_check_field`,
    `cfp`.`daily_bk_average_field` as `daily_bk_average_field`,
    `cfp`.`daily_color_average_field` as `daily_color_average_field`,
    `cfp`.`uc_bk_field` as `uc_bk_field`,
    `cfp`.`uc_cyan_field` as `uc_cyan_field`,
    `cfp`.`uc_magenta_field` as `uc_magenta_field`,
    `cfp`.`uc_yellow_field` as `uc_yellow_field`,
    `cfp`.`cost_center_field` as `cost_center_field`,
    `cfp`.`usage_address_field` as `usage_address_field`,
    `cfp`.`no_invoice_field` as `no_invoice_field`,
    `cfp`.`global_contract_field` as `global_contract_field`
from (((`glpi_printers` `p`
    left join `glpi_infocoms` `i` on(`i`.`items_id` = `p`.`id` and `i`.`itemtype` = 'printer'))
    left join `glpi_suppliers` `s` on(`s`.`id` = `i`.`suppliers_id`))
    left join `glpi_locations` `l` on(`l`.`id` = `p`.`locations_id`))
    left join `glpi_plugin_fields_printerprintercustomfields` cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer';

create or replace view glpi_plugin_iservice_printers_with_last_closed_ticket_data as
select
    `p`.`id` as `id`,
    concat(
            coalesce(concat(`p`.`serial`, ' '), ''),
            '(', `p`.`name`,')',
            coalesce(concat(' - ', `l`.`completename`), ''),
            case
                 when `l`.`completename` = `cfp`.`usage_address_field` then ''
                 else case
                          when coalesce(`cfp`.`usage_address_field`, '') = '' then ''
                          else concat(' | ', `cfp`.`usage_address_field`)
                      end
            end
    ) as `name`,
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
    `p`.`users_id` as `users_id`,
    `p`.`groups_id` as `groups_id`,
    `p`.`states_id` as `states_id`,
    `s`.`id` as `supplier_id`,
    `s`.`name` as `supplier_name`,
    `cfp`.`id` as `cfid`,
    `cfp`.`plugin_fields_containers_id` as `plugin_fields_containers_id`,
    `cfp`.`invoice_date_field` as `invoice_date_field`,
    `cfp`.`invoice_expiry_date_field` as `invoice_expiry_date_field`,
    `cfp`.`invoiced_total_black_field` as `invoiced_total_black_field`,
    `cfp`.`invoiced_total_color_field` as `invoiced_total_color_field`,
    `cfp`.`invoiced_value_field` as `invoiced_value_field`,
    `cfp`.`week_nr_field` as `week_nr_field`,
    `cfp`.`plan_observations_field` as `plan_observations_field`,
    `cfp`.`contact_gps_field` as `contact_gps_field`,
    `cfp`.`em_field` as `em_field`,
    `cfp`.`disable_em_field` as `disable_em_field`,
    `cfp`.`last_read_field` as `last_read_field`,
    `cfp`.`snooze_read_check_field` as `snooze_read_check_field`,
    `cfp`.`daily_bk_average_field` as `daily_bk_average_field`,
    `cfp`.`daily_color_average_field` as `daily_color_average_field`,
    `cfp`.`uc_bk_field` as `uc_bk_field`,
    `cfp`.`uc_cyan_field` as `uc_cyan_field`,
    `cfp`.`uc_magenta_field` as `uc_magenta_field`,
    `cfp`.`uc_yellow_field` as `uc_yellow_field`,
    `cfp`.`cost_center_field` as `cost_center_field`,
    `cfp`.`usage_address_field` as `usage_address_field`,
    `cfp`.`no_invoice_field` as `no_invoice_field`,
    `cfp`.`global_contract_field` as `global_contract_field`,
    `plct`.`effective_date_field` as last_effective_date,
    `plct`.`total2_black_field` as last_total2_black,
    `plct`.`total2_color_field` as last_total2_color,
    `plct`.`effective_date_field` as effective_date
from (((`glpi_printers` `p`
    left join `glpi_infocoms` `i` on(`i`.`items_id` = `p`.`id` and `i`.`itemtype` = 'Printer'))
    left join `glpi_suppliers` `s` on(`s`.`id` = `i`.`suppliers_id`))
    left join `glpi_locations` `l` on(`l`.`id` = `p`.`locations_id`))
    left join `glpi_plugin_fields_printerprintercustomfields` cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer'
    left join `glpi_plugin_iservice_printers_last_closed_tickets` plct on plct.printers_id = p.id;


create or replace view glpi_plugin_iservice_printers_last_tickets as
select
    lt.printers_id
     , lt.tickets_id
     , lt.status
     , cft.effective_date_field
     , cft.total2_black_field
     , cft.total2_color_field
from (
         select
             distinct it.items_id printers_id
                    , first_value(t.id) over w tickets_id
                    , first_value(t.status) over w status
         from glpi_items_tickets it
                  join glpi_tickets t on t.id = it.tickets_id and t.is_deleted = 0
                  join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = t.id and cft.itemtype = 'Ticket'
         where it.itemtype = 'Printer'
         window w as (partition by it.items_id order by cft.effective_date_field desc, t.id desc)
     ) lt
join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = lt.tickets_id and cft.itemtype = 'Ticket';

create or replace view glpi_plugin_iservice_cartridges as
select
    c.id as id,
    c.entities_id as entities_id,
    c.cartridgeitems_id as cartridgeitems_id,
    c.printers_id as printers_id,
    c.date_in as date_in,
    c.date_use as date_use,
    c.date_out as date_out,
    c.pages as pages,
    c.date_mod as date_mod,
    c.date_creation as date_creation,
    cfc.id as cfid,
    cfc.plugin_fields_containers_id as plugin_fields_containers_id,
    cfc.tickets_id_use_field as tickets_id_use_field,
    cfc.tickets_id_out_field as tickets_id_out_field,
    cfc.pages_out_field as pages_out_field,
    cfc.pages_color_out_field as pages_color_out_field,
    cfc.pages_use_field as pages_use_field,
    cfc.pages_color_use_field as pages_color_use_field,
    cfc.suppliers_id_field as suppliers_id_field,
    cfc.locations_id_field as locations_id_field,
    cfc.plugin_fields_cartridgeitemtypedropdowns_id as plugin_fields_cartridgeitemtypedropdowns_id,
    cfc.printed_pages_field as printed_pages_field,
    cfc.printed_pages_color_field as printed_pages_color_field
from glpi_cartridges c
     left join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'Cartridge';

create or replace view glpi_plugin_iservice_cartridge_items as
select
    ci.id as id,
    ci.entities_id as entities_id,
    ci.is_recursive as is_recursive,
    ci.name as name,
    ci.ref as ref,
    ci.locations_id as locations_id,
    ci.cartridgeitemtypes_id as cartridgeitemtypes_id,
    ci.manufacturers_id as manufacturers_id,
    ci.users_id_tech as users_id_tech,
    ci.groups_id_tech as groups_id_tech,
    ci.is_deleted as is_deleted,
    ci.comment as comment,
    ci.alarm_threshold as alarm_threshold,
    ci.stock_target as stock_target,
    ci.date_mod as date_mod,
    ci.date_creation as date_creation,
    ci.pictures as pictures,
    cfci.id as cfid,
    cfci.plugin_fields_containers_id as plugin_fields_containers_id,
    cfci.mercury_code_field as mercury_code_field,
    cfci.compatible_mercury_codes_field as compatible_mercury_codes_field,
    cfci.atc_field as atc_field,
    cfci.plugin_fields_cartridgeitemtypedropdowns_id as plugin_fields_cartridgeitemtypedropdowns_id,
    cfci.life_coefficient_field as life_coefficient_field,
    cfci.supported_types_field as supported_types_field
from glpi_cartridgeitems ci
    left join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = ci.id and cfci.itemtype = 'CartridgeItem';

create or replace view glpi_plugin_iservice_contracts as
select
    c.id as id,
    c.entities_id as entities_id,
    c.is_recursive as is_recursive,
    c.name as name,
    c.num as num,
    c.contracttypes_id as contracttypes_id,
    c.locations_id as locations_id,
    c.begin_date as begin_date,
    c.duration as duration,
    c.notice as notice,
    c.periodicity as periodicity,
    c.billing as billing,
    c.comment as comment,
    c.accounting_number as accounting_number,
    c.is_deleted as is_deleted,
    c.week_begin_hour as week_begin_hour,
    c.week_end_hour as week_end_hour,
    c.saturday_begin_hour as saturday_begin_hour,
    c.saturday_end_hour as saturday_end_hour,
    c.use_saturday as use_saturday,
    c.sunday_begin_hour as sunday_begin_hour,
    c.sunday_end_hour as sunday_end_hour,
    c.use_sunday as use_sunday,
    c.max_links_allowed as max_links_allowed,
    c.alert as alert,
    c.renewal as renewal,
    c.template_name as template_name,
    c.is_template as is_template,
    c.states_id as states_id,
    c.date_mod as date_mod,
    c.date_creation as date_creation,
    cfc.id as cfid,
    cfc.plugin_fields_containers_id as plugin_fields_containers_id,
    cfc.copy_price_bk_field as copy_price_bk_field,
    cfc.copy_price_col_field as copy_price_col_field,
    cfc.included_copies_bk_field as included_copies_bk_field,
    cfc.included_copies_col_field as included_copies_col_field,
    cfc.included_copy_value_field as included_copy_value_field,
    cfc.monthly_fee_field as monthly_fee_field,
    cfc.currency_field as currency_field,
    cfc.copy_price_divider_field as copy_price_divider_field
from glpi_contracts c
    left join glpi_plugin_fields_contractcontractcustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'Contract';

create or replace view plugin_iservice_printer_models as
select
    pm.id as id,
    pm.name as name,
    pm.comment as comment,
    pm.product_number as product_number,
    pm.date_mod as date_mod,
    pm.date_creation as date_creation,
    pm.picture_front as picture_front,
    pm.picture_rear as picture_rear,
    pm.pictures as pictures,
    cfpm.id as cfid,
    cfpm.plugin_fields_containers_id as plugin_fields_containers_id,
    cfpm.em_compatible_field as em_compatible_field
from glpi_printermodels pm
    left join glpi_plugin_fields_printermodelprintermodelcustomfields cfpm on cfpm.items_id = pm.id and cfpm.itemtype = 'PrinterModel';

create or replace view glpi_plugin_iservice_suppliers as
select
    s.id as id,
    s.entities_id as entities_id,
    s.is_recursive as is_recursive,
    s.name as name,
    s.suppliertypes_id as suppliertypes_id,
    s.registration_number as registration_number,
    s.address as address,
    s.postcode as postcode,
    s.town as town,
    s.state as state,
    s.country as country,
    s.website as website,
    s.phonenumber as phonenumber,
    s.comment as comment,
    s.is_deleted as is_deleted,
    s.fax as fax,
    s.email as email,
    s.date_mod as date_mod,
    s.date_creation as date_creation,
    s.is_active as is_active,
    s.pictures as pictures,
    cfs.id as cfid,
    cfs.plugin_fields_containers_id as plugin_fields_containers_id,
    cfs.uic_field as uic_field,
    cfs.crn_field as crn_field,
    cfs.intervention_sheet_model_field as intervention_sheet_model_field,
    cfs.hmarfa_code_field as hmarfa_code_field,
    cfs.address_field as address_field,
    cfs.email_for_invoices_field as email_for_invoices_field,
    cfs.payment_deadline_field as payment_deadline_field,
    cfs.cm_field as cm_field,
    cfs.magic_link_field as magic_link_field,
    cfs.group_field as group_field,
    cfs.force_location_parent_field as force_location_parent_field
from glpi_suppliers s
    left join glpi_plugin_fields_suppliersuppliercustomfields cfs on cfs.items_id = s.id and cfs.itemtype = 'Supplier';

create or replace view glpi_plugin_iservice_consumable_compatible_printers_counts as
select c.id, count(distinct (p.id)) `count`, group_concat(concat(p.name, ' (', p.id, ')') separator '\\n') as pids
from glpi_plugin_iservice_cartridges c
    left join glpi_locations l1 on l1.id = c.locations_id_field
    left join glpi_plugin_fields_suppliersuppliercustomfields cfs on cfs.items_id = c.suppliers_id_field and cfs.itemtype = 'supplier'
    join glpi_cartridgeitems_printermodels cp on cp.cartridgeitems_id = c.cartridgeitems_id
    join glpi_printers p on p.printermodels_id = cp.printermodels_id
    left join glpi_locations l2 on l2.id = p.locations_id
    join glpi_infocoms ic on ic.itemtype = 'printer'
        and ic.items_id = p.id
        and find_in_set (ic.suppliers_id, cfs.group_field)
where p.is_deleted = 0
  and (c.locations_id_field = p.locations_id or coalesce(l1.locations_id, 0) = coalesce(l2.locations_id, 0))
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

create or replace view glpi_plugin_iservice_printer_unclosed_ticket_counts as
select
    it.items_id printers_id
     , count(it.tickets_id) ticket_count
     , max(tcf.effective_date_field) max_effective_date
     , min(tcf.effective_date_field) min_effective_date
from glpi_items_tickets it
         join glpi_tickets t on t.id = it.tickets_id and not t.status = 6 and t.is_deleted = 0
         join glpi_plugin_fields_ticketticketcustomfields tcf on tcf.items_id = t.id and tcf.itemtype = 'Ticket'
where it.itemtype = 'Printer'
group by it.items_id;

create or replace view glpi_plugin_iservice_printer_closed_ticket_counts as
select
    it.items_id printers_id
     , count(it.tickets_id) ticket_count
     , max(tcf.effective_date_field) max_effective_date
     , min(tcf.effective_date_field) min_effective_date
from glpi_items_tickets it
         join glpi_tickets t on t.id = it.tickets_id and t.status = 6 and t.is_deleted = 0
         join glpi_plugin_fields_ticketticketcustomfields tcf on tcf.items_id = t.id and tcf.itemtype = 'Ticket'
where it.itemtype = 'Printer'
group by it.items_id;

create or replace view glpi_plugin_iservice_consumable_changeable_counts as
select c1.id, count(distinct c2.id) `count`, group_concat(concat(ci2.ref, ' (', c2.id, ')') separator '\\n') as cids
from glpi_plugin_iservice_cartridges c1
    left join glpi_locations l1 on l1.id = c1.locations_id_field
    join glpi_infocoms ic on ic.items_id = c1.printers_id and ic.itemtype = 'Printer'
    join glpi_plugin_fields_suppliersuppliercustomfields cfs on cfs.items_id = ic.suppliers_id and cfs.itemtype = 'Supplier'
    join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci1 on cfci1.items_id = c1.cartridgeitems_id and cfci1.itemtype = 'CartridgeItem'
    join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci2 on find_in_set(cfci2.mercury_code_field, replace(cfci1.compatible_mercury_codes_field, "'", "")) and cfci2.itemtype = 'CartridgeItem'
    join glpi_plugin_iservice_cartridges c2 on c2.cartridgeitems_id = cfci2.items_id
    join glpi_cartridgeitems ci2 on ci2.id = cfci2.items_id
    left join glpi_locations l2 on l2.id = c2.locations_id_field
where c2.date_use is null and c2.date_out is null
    and find_in_set (c2.suppliers_id_field, cfs.group_field)
    and coalesce(c2.printers_id, 0) = 0
    and (c1.plugin_fields_cartridgeitemtypedropdowns_id  = c2.plugin_fields_cartridgeitemtypedropdowns_id  or coalesce(cfci2.plugin_fields_cartridgeitemtypedropdowns_id , 0) = 0)
    and (c2.locations_id_field = c1.locations_id_field or coalesce(l1.locations_id, 0) = coalesce(l2.locations_id, 0))
group by c1.id;

create or replace view glpi_plugin_iservice_printer_usage_coefficients as
select
    c.printers_id printers_id
     , ccf.plugin_fields_cartridgeitemtypedropdowns_id
     , round(avg(if(ccf.plugin_fields_cartridgeitemtypedropdowns_id in (2, 3, 4), ccf.printed_pages_color_field, ccf.printed_pages_color_field + ccf.printed_pages_field)) / avg(cicf.atc_field), 2) uc
     , cicf.atc_field
     , group_concat(concat('- între ', c.date_use, ' și ', c.date_out, ' au fost printate ', if(`ccf`.`plugin_fields_cartridgeitemtypedropdowns_id` in (2, 3, 4), `ccf`.`printed_pages_color_field`, `ccf`.`printed_pages_color_field` + `ccf`.`printed_pages_field`), ' pagini cu cartușul ', c.id) separator '\n') as `explanation`
from glpi_cartridges c
    join glpi_cartridgeitems ci on ci.id = c.cartridgeitems_id and (ci.ref like '%cton%' or ci.ref like '%ccat%')
    join glpi_plugin_fields_cartridgecartridgecustomfields ccf on ccf.items_id = c.cartridgeitems_id and ccf.itemtype = 'Cartridge'
    join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cicf on cicf.items_id = c.cartridgeitems_id and cicf.itemtype = 'CartridgeItem'
where c.date_in is not null
  and c.date_use is not null
  and c.date_out is not null
  and if(ccf.plugin_fields_cartridgeitemtypedropdowns_id in (2,3,4), ccf.printed_pages_color_field, ccf.printed_pages_color_field + ccf.printed_pages_field) > 0
group by c.printers_id, ccf.plugin_fields_cartridgeitemtypedropdowns_id;

