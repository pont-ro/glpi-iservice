CREATE TABLE IF NOT EXISTS `glpi_plugin_iservice_configs`
(
    `id`    int UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`  varchar(150) NOT NULL,
    `value` text,
    PRIMARY KEY (`id`),
    INDEX `name` (`name`)
);

INSERT INTO `glpi_plugin_iservice_configs` (`name`, `value`)
VALUES ('version', '0');
