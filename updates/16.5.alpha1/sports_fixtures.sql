/* Add Service permission for Sports Admin
 */
INSERT INTO `luServices` (`id`, `name`) VALUES (10, 'Sports Administrator') ON DUPLICATE KEY UPDATE `id` = `id`;

/* Sport Table
 */
CREATE TABLE `sportsfixtures_sport` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`name` VARCHAR(100) NULL COMMENT '',
	`url` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

/* Division Table
 */
CREATE TABLE `sportsfixtures_division` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`sport_id` INT NULL COMMENT '',
	`name` VARCHAR(255) NULL COMMENT '',
	`url` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

/* Team Table
 */
CREATE TABLE `sportsfixtures_team` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`division_id` INT NULL COMMENT '',
	`name` VARCHAR(255) NULL COMMENT '',
	`url` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

/* Position Table
 */
CREATE TABLE `sportsfixtures_position` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`sport_id` INT NULL COMMENT '',
	`name` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

