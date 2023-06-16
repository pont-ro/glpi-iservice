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

CREATE TABLE IF NOT EXISTS `glpi_plugin_iservice_importmappings`
(
    `id`       int UNSIGNED NOT NULL AUTO_INCREMENT,
    `itemtype` varchar(255) NOT NULL,
    `items_id` int UNSIGNED NOT NULL,
    `old_id`   int UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `item` (`itemtype`, `items_id`),
    UNIQUE INDEX `old_item` (`itemtype`, `old_id`)
);
