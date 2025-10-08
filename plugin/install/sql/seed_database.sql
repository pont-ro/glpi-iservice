insert into `glpi_plugin_iservice_configs` (`name`, `value`)
select 'version', '0' from dual
where not exists (select 1 from `glpi_plugin_iservice_configs` where `name` = 'version');

-- Create a new contract type: 'Gsm'
insert into `glpi_contracttypes` (`name`, `date_mod`, `date_creation`)
select 'Gsm', now(), now() from dual
where not exists (select 1 from `glpi_contracttypes` where `name` = 'Gsm');

-- Add this new contract type to all the contracts that have 'VDF' in their names
UPDATE glpi_contracts c
SET c.contracttypes_id = (select ct.id from glpi_contracttypes ct where ct.name = 'Gsm')
WHERE c.is_deleted = 0 and c.name like '%vdf%' and coalesce(c.contracttypes_id, 0) = 0;