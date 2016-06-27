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


/** Division players table **/
CREATE TABLE `sportsfixtures_division_user` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`division_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`sequence` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	INDEX `fk_sportsfixtures_division_user__division_idx` (`division_id` ASC),
	INDEX `sportsfixtures_division_user__user` (`user_id` ASC),
	CONSTRAINT `fk_sportsfixtures_division_user__division`
	FOREIGN KEY (`division_id`)
	REFERENCES `sportsfixtures_division` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE
	) ENGINE=InnoDB CHARACTER SET=utf8;

/* Venue Table */
-- Lat/Long 6 decimal places -> 10cm accuracy
-- 9 decimal digits -> 4 bytes storage
-- Latitude:   -90.000000 ->  +90.000000
-- Longitude: -180.000000 -> +180.000000
CREATE TABLE `sportsfixtures_venue` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`name` VARCHAR(255) NOT NULL COMMENT '',
	`latitude` DECIMAL(8,6) NULL COMMENT '',
	`longitude` DECIMAL(9,6) NULL COMMENT '',
	`address` VARCHAR(255) NOT NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;
