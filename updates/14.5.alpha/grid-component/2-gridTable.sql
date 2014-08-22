-- The grid component
CREATE TABLE `grid` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `layout_type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: grid, 2: column',
  `grid_width` INT(4) NOT NULL DEFAULT 0,
  `column_count` TINYINT(1) NOT NULL DEFAULT 1,
  `aspect_ratio_x` TINYINT(2) NOT NULL DEFAULT 16,
  `aspect_ratio_y` TINYINT(2) NOT NULL DEFAULT 9,
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  `deleted_at` DATETIME,
  PRIMARY KEY (`id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;
