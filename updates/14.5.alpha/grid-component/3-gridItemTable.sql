-- The grid item table
CREATE TABLE `grid_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `grid_id` INT(11) NOT NULL,
  `title_text` VARCHAR(250) NOT NULL,
  `title_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  `title_background_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  `title_position` TINYINT(1) NOT NULL COMMENT '1: top, 2: bottom',
  `link` VARCHAR(250) NOT NULL,
  `background_image` VARCHAR(250) NOT NULL,
  `background_color` VARCHAR(8) NOT NULL COMMENT 'RRGGBBAA',
  PRIMARY KEY (`id`),
  CONSTRAINT `grid_item_grid`
    FOREIGN KEY (`grid_id`)
    REFERENCES `grid` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;
