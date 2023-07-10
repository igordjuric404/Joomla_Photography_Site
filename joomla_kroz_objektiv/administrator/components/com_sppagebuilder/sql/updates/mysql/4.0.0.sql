-- v4.0.0
CREATE TABLE IF NOT EXISTS `#__sppagebuilder_assets` (
	`id` bigint NOT NULL AUTO_INCREMENT,
	`type` varchar(100) NOT NULL DEFAULT '',
	`name` varchar(100) NOT NULL DEFAULT '',
	`title` varchar(255) NOT NULL DEFAULT '',
	`assets` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`css_path` text,
	`created` datetime NOT NULL,
	`created_by` int NOT NULL,
	`published` tinyint(1) NOT NULL DEFAULT '1',
	`access` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__sppagebuilder_addonlist` (
	`id` int(5) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(255) NOT NULL,
	`ordering` int(5) NOT NULL DEFAULT '0',
	`status` tinyint(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `#__spmedia` MODIFY `created_on` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__spmedia` MODIFY `modified_on` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__sppagebuilder` MODIFY `created_on` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__sppagebuilder` MODIFY `modified` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__sppagebuilder` MODIFY `checked_out_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__sppagebuilder_sections` MODIFY `created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__sppagebuilder_addons` MODIFY `created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';

UPDATE `#__spmedia` SET `created_on` = '0000-00-00 00:00:00' WHERE `created_on` = NULL;
UPDATE `#__spmedia` SET `modified_on` = '0000-00-00 00:00:00' WHERE `modified_on` = NULL;

UPDATE `#__sppagebuilder` SET `created_on` = '0000-00-00 00:00:00' WHERE `created_on` = NULL;
UPDATE `#__sppagebuilder` SET `modified` = '0000-00-00 00:00:00' WHERE `modified` = NULL;
UPDATE `#__sppagebuilder` SET `checked_out_time` = '0000-00-00 00:00:00' WHERE `checked_out_time` = NULL;

UPDATE `#__sppagebuilder_sections` SET `created` = '0000-00-00 00:00:00' WHERE `created` = NULL;
UPDATE `#__sppagebuilder_addons` SET `created` = '0000-00-00 00:00:00' WHERE `created` = NULL;