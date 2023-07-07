create table if not exists `glpi_plugin_iservice_configs`
(
    `id`    int unsigned not null auto_increment,
    `name`  varchar(150) not null,
    `value` text,
    primary key (`id`),
    index `name` (`name`)
);

insert into `glpi_plugin_iservice_configs` (`name`, `value`)
values ('version', '0');

create table if not exists `glpi_plugin_iservice_importmappings`
(
    `id`       int unsigned not null auto_increment,
    `itemtype` varchar(255) not null,
    `items_id` int unsigned not null,
    `old_id`   int unsigned not null,
    primary key (`id`),
    unique index `item` (`itemtype`, `items_id`),
    unique index `old_item` (`itemtype`, `old_id`)
);

create table if not exists `hmarfa_facrind` (
    `tip` varchar(5) null default null,
    `nrfac` varchar(6) null default null,
    `grupa` varchar(6) null default null,
    `codmat` varchar(15) null default null,
    `gest` varchar(4) null default null,
    `nrtran` varchar(6) null default null,
    `cent` varchar(4) null default null,
    `centlot` varchar(11) null default null,
    `descr` varchar(125) null default null,
    `obs1` varchar(30) null default null,
    `cant` double null default null,
    `pucont` double null default null,
    `puini` double null default null,
    `puliv` double null default null,
    `puvin` double null default null,
    `pudol` double null default null,
    `vbaza` double null default null,
    `tvan` int(11) null default null,
    `icm` int(11) null default null,
    `tvapoz` varchar(3) null default null,
    `vtva` double null default null,
    `acc` int(11) null default null,
    `nb_upd` int(11) null default null,
    index `nrtran` (`nrtran`),
    index `codmat` (`codmat`)
);

create table if not exists `hmarfa_facturi` (
    `nrjur` varchar(5) null default null,
    `nrfac` varchar(6) not null,
    `nrrs` varchar(15) null default null,
    `nrsec` varchar(6) null default null,
    `factva` enum('f','t') null default null,
    `datafac` date null default null,
    `dscad` date null default null,
    `dscadtva` date null default null,
    `tipbenef` varchar(1) null default null,
    `codbenef` varchar(7) null default null,
    `tvaibenef` varchar(1) null default null,
    `cif` varchar(15) null default null,
    `stare` varchar(3) null default null,
    `nrcmd` varchar(12) null default null,
    `centdoc` varchar(11) null default null,
    `deleg` varchar(5) null default null,
    `tip` varchar(5) null default null,
    `model` varchar(1) null default null,
    `valvin` double null default null,
    `valliv` double null default null,
    `dolliv` double null default null,
    `valinc` double null default null,
    `dolinc` double null default null,
    `valrev` double null default null,
    `valrev2` double null default null,
    `valprv2` double null default null,
    `moneda` varchar(3) null default null,
    `curs` double null default null,
    `valpla` double null default null,
    `numerar` double null default null,
    `card` double null default null,
    `tichet` double null default null,
    `dolpla` double null default null,
    `valinr` double null default null,
    `valicm` double null default null,
    `tvai` double null default null,
    `valacc` double null default null,
    `cont` varchar(11) null default null,
    `txtlib` text null default null,
    `codl` varchar(10) null default null,
    `optva` varchar(5) null default null,
    `nb_upd` int(11) null default null,
    primary key (`nrfac`),
    index `nrfac` (`nrfac`),
    index `codbenef` (`codbenef`)
);

create table if not exists `hmarfa_firme` (
    `cod` varchar(7) not null,
    `initiale` varchar(30) null default null,
    `tip` varchar(10) null default null,
    `denum` varchar(50) null default null,
    `cod1` varchar(15) null default null,
    `cod2` varchar(15) null default null,
    `contdb` varchar(11) null default null,
    `contcr` varchar(11) null default null,
    `codpostal` varchar(7) null default null,
    `localitate` varchar(20) null default null,
    `adrs1` varchar(40) null default null,
    `adrs2` varchar(40) null default null,
    `adrp1` varchar(40) null default null,
    `adrp2` varchar(40) null default null,
    `tel1` varchar(15) null default null,
    `tel2` varchar(15) null default null,
    `fax` varchar(15) null default null,
    `telex` varchar(40) null default null,
    `web` varchar(40) null default null,
    `banca` varchar(35) null default null,
    `cont` varchar(29) null default null,
    `obs` text null default null,
    `nb_upd` int(11) null default null,
    `zile_scad` int(11) null default null,
    `tip_pret` varchar(1) null default null,
    `credit_lim` int(11) null default null,
    primary key (`cod`)
);

create table if not exists `hmarfa_gestiuni` (
    `cod` varchar(4) null default null,
    `denum` varchar(30) null default null,
    `descr` varchar(100) null default null,
    `nb_upd` int(11) null default null
);

create table if not exists `hmarfa_incpla` (
    `nrjur` varchar(5) null default null,
    `nrfila` varchar(6) null default null,
    `data` date null default null,
    `tipip` varchar(1) null default null,
    `tipdoc` varchar(5) null default null,
    `model` varchar(1) null default null,
    `nrdoc` varchar(20) null default null,
    `datadoc` date null default null,
    `nrfac` varchar(6) null default null,
    `tipfact` varchar(1) null default null,
    `centdoc` varchar(11) null default null,
    `obs1fac` varchar(40) null default null,
    `obs2fac` varchar(40) null default null,
    `tippart` varchar(1) null default null,
    `partener` varchar(7) null default null,
    `obspart` varchar(40) null default null,
    `sumaval` double null default null,
    `moneda` varchar(3) null default null,
    `suma` double null default null,
    `sumafac` double null default null,
    `sumarev` double null default null,
    `sumaprv2` double null default null,
    `sumarev2` double null default null,
    `tvaip` double null default null,
    `contcr` varchar(11) null default null,
    `stare` varchar(3) null default null,
    `codl` varchar(10) null default null,
    `nb_upd` int(11) null default null,
    index `partener` (`partener`)
);

create table if not exists `hmarfa_lotm` (
    `nrtran` varchar(6) null default null,
    `codmat` varchar(15) null default null,
    `gest` varchar(4) null default null,
    `centlot` varchar(11) null default null,
    `umamb` varchar(3) null default null,
    `fctcv` double null default null,
    `tip` varchar(1) null default null,
    `tipgest` varchar(2) null default null,
    `metstoc` varchar(4) null default null,
    `grupa` varchar(6) null default null,
    `pachdol` double null default null,
    `moneda` varchar(3) null default null,
    `pach` double null default null,
    `pinvama` double null default null,
    `pvama` double null default null,
    `pchelt` double null default null,
    `paccr` double null default null,
    `ptaxe` double null default null,
    `pini` double null default null,
    `pinr` double null default null,
    `pliv` double null default null,
    `pvin` double null default null,
    `pcont` double null default null,
    `cont` varchar(11) null default null,
    `stoci` double null default null,
    `iesiri` double null default null,
    `cist` double null default null,
    `dataexp` date null default null,
    `obs` varchar(20) null default null,
    `nb_upd` int(11) null default null,
    index `nrtran` (`nrtran`),
    index `codmat` (`codmat`)
);

create table if not exists `hmarfa_nommarfa` (
    `cod` varchar(15) not null,
    `denum` varchar(30) null default null,
    `descr` varchar(60) null default null,
    `um` varchar(3) null default null,
    `masan` double null default null,
    `grupa` varchar(6) null default null,
    `cod_ech` varchar(5) null default null,
    `vama` double null default null,
    `vamapoz` varchar(10) null default null,
    `tva` int(11) null default null,
    `tvapoz` varchar(6) null default null,
    `acz` int(11) null default null,
    `aczpoz` varchar(4) null default null,
    `icm` int(11) null default null,
    `icmpoz` varchar(6) null default null,
    `acc` int(11) null default null,
    `accpoz` varchar(4) null default null,
    `prod_txi` varchar(13) null default null,
    `prod_tx0` varchar(13) null default null,
    `caen_act` varchar(8) null default null,
    `pvinv` double null default null,
    `moneda` varchar(3) null default null,
    `pvin` double null default null,
    `pvina` double null default null,
    `nb_upd` int(11) null default null,
    `cod_produc` varchar(20) null default null,
    `p_preturi` enum('f','t') null default null,
    `p_pret1` double null default null,
    `p_pret2` double null default null,
    `p_pret3` double null default null,
    primary key (`cod`)
);

create table if not exists `hmarfa_tran` (
    `nrjur` varchar(5) null default null,
    `nrtran` varchar(6) not null,
    `gest` varchar(4) null default null,
    `tipdcm` varchar(5) null default null,
    `model` varchar(1) null default null,
    `dataint` date null default null,
    `nrcmd` varchar(12) null default null,
    `tipfurn` varchar(1) null default null,
    `furnizor` varchar(7) null default null,
    `nrdoc` varchar(20) null default null,
    `datadoc` date null default null,
    `fdscad` date null default null,
    `valdol` double null default null,
    `pladol` double null default null,
    `moneda` varchar(3) null default null,
    `val` double null default null,
    `valrev` double null default null,
    `tva` double null default null,
    `pla` double null default null,
    `cont` varchar(11) null default null,
    `furvama` varchar(7) null default null,
    `nrvama` varchar(20) null default null,
    `datavama` date null default null,
    `vdscad` date null default null,
    `valvama` double null default null,
    `txvama` double null default null,
    `tvavama` double null default null,
    `acc` double null default null,
    `plavama` double null default null,
    `contvama` varchar(11) null default null,
    `furchel` varchar(7) null default null,
    `nrchel` varchar(20) null default null,
    `datachel` date null default null,
    `cdscad` date null default null,
    `valchel` double null default null,
    `tvachel` double null default null,
    `plachel` double null default null,
    `contchel` varchar(11) null default null,
    `stare` varchar(3) null default null,
    `optva` varchar(5) null default null,
    primary key (`nrtran`),
    index `hmarfa_tran_dataint_idx` (`dataint`)
);

create table `glpi_plugin_iservice_consumables_tickets` (
    `id` int(11) not null auto_increment,
    `locations_id` int(11) not null default '0',
    `create_cartridge` tinyint(1) not null default '0',
    `tickets_id` int(11) not null default '0',
    `plugin_iservice_consumables_id` varchar(15) not null default '0',
    `plugin_fields_typefielddropdowns_id` int(11) null default null,
    `amount` decimal(11,2) not null default '0.00',
    `price` decimal(11,2) not null default '0.00',
    `euro_price` tinyint(1) not null default '0',
    `new_cartridge_ids` varchar(200) null default null,
    primary key (`id`),
    unique index `unique_ticket_consumables` (`tickets_id`, `plugin_iservice_consumables_id`),
    index `tickets_id` (`tickets_id`),
    index `locations_id` (`locations_id`),
    index `new_cartridge_ids` (`new_cartridge_ids`),
    index `amount` (`amount`),
    index `plugin_iservice_consumables_id` (`plugin_iservice_consumables_id`),
    index `plugin_fields_typefielddropdowns_id` (`plugin_fields_typefielddropdowns_id`)
);

create table `glpi_plugin_iservice_cartridges_tickets` (
    `id` int(11) not null auto_increment,
    `tickets_id` int(11) not null default '0',
    `cartridges_id` int(11) not null default '0',
    `locations_id` int(11) not null default '0',
    `plugin_fields_typefielddropdowns_id` int(11) not null default '0',
    `cartridges_id_emptied` int(11) null default null,
    primary key (`id`),
    unique index `unique_ticket_cartridges` (`tickets_id`, `cartridges_id`),
    index `tickets_id` (`tickets_id`),
    index `cartridges_id` (`cartridges_id`),
    index `locations_id` (`locations_id`),
    index `plugin_fields_typefielddropdowns_id` (`plugin_fields_typefielddropdowns_id`),
    index `cartridges_id_emptied` (`cartridges_id_emptied`)
);

create table `glpi_plugin_iservice_intorders` (
    `id` int(11) not null auto_increment,
    `tickets_id` int(11) null default null,
    `plugin_iservice_consumables_id` varchar(15) not null default '0',
    `amount` decimal(11,2) not null,
    `deadline` date not null default '0000-00-00',
    `users_id` int(11) not null,
    `plugin_iservice_orderstatuses_id` int(11) not null default '0',
    `content` text null default null,
    `create_date` timestamp not null default current_timestamp(),
    `modify_date` timestamp not null default '0000-00-00 00:00:00',
    primary key (`id`),
    index `plugin_frontim_consumables_id` (`plugin_iservice_consumables_id`),
    index `deadline` (`deadline`),
    index `users_id` (`users_id`),
    index `plugin_frontim_orderstatues_id` (`plugin_iservice_orderstatuses_id`),
    index `tickets_id` (`tickets_id`),
    index `plugin_iservice_consumables_id` (`plugin_iservice_consumables_id`),
    index `plugin_iservice_orderstatues_id` (`plugin_iservice_orderstatuses_id`)
);

create table `glpi_plugin_iservice_intorders_extorders` (
    `id` int(11) not null auto_increment,
    `plugin_iservice_extorders_id` int(11) not null,
    `plugin_iservice_intorders_id` int(11) not null,
    primary key (`id`),
    index `plugin_frontim_extorders_id` (`plugin_iservice_extorders_id`),
    index `plugin_frontim_intorders_id` (`plugin_iservice_intorders_id`),
    index `plugin_iservice_extorders_id` (`plugin_iservice_extorders_id`),
    index `plugin_iservice_intorders_id` (`plugin_iservice_intorders_id`)
);

create table `glpi_plugin_iservice_orderstatuses` (
    `id` int(11) not null auto_increment,
    `name` varchar(100) not null,
    `comment` text null default null,
    `weight` int(4) not null default '0',
    primary key (`id`)
);

create table `glpi_plugin_iservice_ememails` (
    `id` int(11) not null auto_increment,
    `date` timestamp null default null,
    `from` varchar(100) null default null,
    `to` varchar(100) null default null,
    `subject` varchar(255) null default null,
    `body` text null default null,
    `printers_id` int(11) null default null,
    `suppliers_id` int(11) null default null,
    `users_id_tech` int(11) null default null,
    `suggested` text null default null,
    `process_result` text null default null,
    `read` int(1) not null default '0',
    primary key (`id`)
);

create table `glpi_plugin_iservice_extorders` (
    `id` int(11) not null auto_increment,
    `users_id` int(11) not null,
    `suppliers_id` int(11) not null,
    `plugin_iservice_orderstatuses_id` int(11) not null,
    `content` text null default null,
    `create_date` timestamp not null default current_timestamp(),
    `modify_date` timestamp not null default '0000-00-00 00:00:00',
    primary key (`id`),
    index `users_id` (`users_id`),
    index `suppliers_id` (`suppliers_id`),
    index `plugin_frontim_orderstatues_id` (`plugin_iservice_orderstatuses_id`),
    index `plugin_iservice_orderstatues_id` (`plugin_iservice_orderstatuses_id`)
);

create table `glpi_plugin_iservice_orderstatuschanges` (
    `id` int(11) not null auto_increment,
    `orders_id` int(11) not null,
    `type` varchar(50) not null,
    `plugin_iservice_orderstatuses_id_old` int(11) not null,
    `plugin_iservice_orderstatuses_id_new` int(11) not null,
    `users_id` int(11) not null,
    `date` timestamp not null default current_timestamp(),
    primary key (`id`),
    index `orders_id` (`orders_id`),
    index `type` (`type`),
    index `orderstatuses_id_old` (`plugin_iservice_orderstatuses_id_old`),
    index `orderstatuses_id_new` (`plugin_iservice_orderstatuses_id_new`)
);

create table `glpi_plugin_iservice_consumables_models` (
    `id` int(11) not null auto_increment,
    `plugin_iservice_consumables_id` varchar(15) not null default '0',
    `printermodels_id` int(11) not null default '0',
    primary key (`id`)
);

create table `glpi_plugin_iservice_minimum_stocks` (
	`id` int(11) not null auto_increment,
	`plugin_iservice_consumables_id` varchar(15) not null default '0',
	`minimum_stock` int(11) not null default '0',
	primary key (`id`)
);

create table `glpi_plugin_iservice_movements` (
    `id` int(11) not null auto_increment,
    `itemtype` varchar(255) not null,
    `tickets_id` int(11) not null,
    `items_id` int(11) not null,
    `suppliers_id_old` int(11) not null,
    `suppliers_id` int(11) not null,
    `init_date` timestamp not null default current_timestamp(),
    `invoice` int(1) null default null,
    `users_id` int(11) null default null,
    `states_id` int(11) null default null,
    `locations_id` int(11) null default null,
    `usage_address` varchar(255) null default null,
    `week_number` int(11) null default null,
    `users_id_tech` int(11) null default null,
    `groups_id` int(11) null default null,
    `contact` varchar(255) null default null,
    `contact_num` varchar(255) null default null,
    `contracts_id` int(11) null default null,
    `dba` decimal(10,0) null default null,
    `dca` decimal(10,0) null default null,
    `total2_black_fact` int(11) null default null,
    `total2_color_fact` int(11) null default null,
    `data_fact` date null default null,
    `data_exp_fact` date null default null,
    `disableem` tinyint(1) not null default '0',
    `snoozereadcheck` date null default null,
    `moved` int(1) not null default '0',
    `type` varchar(20) not null,
    primary key (`id`),
    index `tickets_id` (`tickets_id`),
    index `items_id` (`items_id`),
    index `itemtype` (`itemtype`),
    index `suppliers_id_old` (`suppliers_id_old`),
    index `suppliers_id` (`suppliers_id`)
);

create table `glpi_plugin_iservice_downloads` (
    `id` int(11) not null auto_increment,
    `downloadtype` varchar(20) not null default '',
    `items_id` varchar(50) not null default '',
    `date` timestamp not null default current_timestamp(),
    `ip` varchar(15) not null default '0.0.0.0',
    `users_id` int(11) not null default '0',
    primary key (`id`),
    index `downloadtype` (`downloadtype`),
    index `items_id` (`items_id`),
    index `users_id` (`users_id`)
);

create table `glpi_plugin_iservice_pendingemails` (
    `id` int(11) not null auto_increment,
    `printers_id` int(11) not null,
    `refresh_time` timestamp not null default current_timestamp(),
    `invoice` varchar(20) null default null,
    `mail_to` varchar(200) null default null,
    `subject` varchar(100) null default null,
    `body` text null default null,
    `attachment` varchar(250) null default null,
    primary key (`id`),
    index `printers_id` (`printers_id`)
);
