-- The grid item table
CREATE TABLE `grid_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `grid_id` INT(11) NOT NULL,
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
  CONSTRAINT `grid_item_grid`
    FOREIGN KEY (`grid_id`)
    REFERENCES `grid` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;
