-- The tile container item table
CREATE TABLE `tile` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tile_layout_id` INT(11) NOT NULL,
  `title_text` VARCHAR(250) NOT NULL,
  `title_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  `title_background_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  `title_position` TINYINT(1) NOT NULL COMMENT '1: top, 2: bottom',
  `link` VARCHAR(250) NOT NULL,
  `background_image_id` INT(11) NULL,
  `background_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  `deleted_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX (`background_image_id`),
  CONSTRAINT `tile_tile_layout`
    FOREIGN KEY (`tile_layout_id`)
    REFERENCES `tile_layout` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;
