CREATE TABLE `market` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`game` INT(11) NOT NULL,
	`classid` BIGINT(20) NOT NULL,
	`instanceid` BIGINT(20) NOT NULL,
	`price` DECIMAL(19,2) NOT NULL,
	`offers` INT(11) NOT NULL,
	`rarity` VARCHAR(30) NOT NULL COLLATE 'utf8_unicode_ci',
	`quality` VARCHAR(30) NOT NULL COLLATE 'utf8_unicode_ci',
	`heroid` INT(11) NOT NULL,
	`slot` VARCHAR(30) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`market_name` VARCHAR(255) NOT NULL COLLATE 'utf8_unicode_ci',
	`description` TEXT NULL COLLATE 'utf8_unicode_ci',
	`img_small` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `market_game_index` (`game`)
)
COLLATE='utf8_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=0
;
