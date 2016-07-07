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
	PRIMARY KEY (`id`),
	FOREIGN KEY (`sport_id`)
	REFERENCES `sportsfixtures_sport`(`id`)
		ON UPDATE NO ACTION
		ON DELETE NO ACTION
) ENGINE=InnoDB CHARACTER SET=utf8;

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
	PRIMARY KEY (`id`),
	FOREIGN KEY (`division_id`)
	REFERENCES `sportsfixtures_division`(`id`)
		ON UPDATE NO ACTION
		ON DELETE NO ACTION
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Position Table
 */
CREATE TABLE `sportsfixtures_position` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`sport_id` INT NULL COMMENT '',
	`name` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`sport_id`)
	REFERENCES `sportsfixtures_sport`(`id`)
		ON UPDATE NO ACTION
		ON DELETE NO ACTION
) ENGINE=InnoDB CHARACTER SET=utf8;


/** Division players table **/
CREATE TABLE `sportsfixtures_division_user` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`division_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`sequence` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `sportsfixtures_division_user__division_user` (`division_id` ASC, `user_id` ASC),
	INDEX `fk_sportsfixtures_division_user__division_idx` (`division_id` ASC),
	INDEX `sportsfixtures_division_user__user` (`user_id` ASC),
	CONSTRAINT `fk_sportsfixtures_division_user__division`
	FOREIGN KEY (`division_id`)
	REFERENCES `sportsfixtures_division` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE
	) ENGINE=InnoDB CHARACTER SET=utf8;

/* Venue Table */
-- Lat/Long 7 decimal places -> 1cm accuracy (matches Google API return types)
-- Latitude:   -90.0000000 ->  +90.0000000
-- Longitude: -180.0000000 -> +180.0000000
CREATE TABLE `sportsfixtures_venue` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`name` VARCHAR(255) NOT NULL COMMENT '',
	`latitude` DECIMAL(9,7) NULL COMMENT '',
	`longitude` DECIMAL(10,7) NULL COMMENT '',
	`address` VARCHAR(255) NOT NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

/* Match Table
 */
CREATE TABLE `sportsfixtures_match` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`division_id` INT NOT NULL,
	`venue_id` INT NOT NULL,
	`home_team_id` INT,
	`away_team_id` INT NULL,
	`home_school_id` INT NULL,
	`away_school_id` INT NULL,
	`name` VARCHAR(255) NULL COMMENT '',
	`start_at` DATETIME NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`division_id`)
		REFERENCES `sportsfixtures_division`(`id`)
			ON UPDATE NO ACTION
			ON DELETE NO ACTION,
	FOREIGN KEY (`venue_id`)
		REFERENCES `sportsfixtures_venue`(`id`)
			ON UPDATE NO ACTION
			ON DELETE NO ACTION,
	FOREIGN KEY (`home_team_id`)
		REFERENCES `sportsfixtures_team`(`id`)
			ON UPDATE NO ACTION
			ON DELETE NO ACTION,
	FOREIGN KEY (`away_team_id`)
		REFERENCES `sportsfixtures_team`(`id`)
			ON UPDATE NO ACTION
			ON DELETE NO ACTION
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Match ItineraryItem Table
 */
CREATE TABLE `sportsfixtures_match_itineraryitem` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`match_id` INT NOT NULL,
	`description` TEXT NULL COMMENT '',
	`start_at` DATETIME NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`match_id`)
		REFERENCES `sportsfixtures_match`(`id`)
			ON UPDATE NO ACTION
			ON DELETE NO ACTION
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Error table entries */
INSERT INTO `error` VALUES
    (173, 'The requested sport could not be found.'),
    (174, 'The requested division could not be found.'),
    (175, 'The requested team could not be found.'),
    (176, 'The requested venue could not be found.'),
		(177, 'The requested match could not be found.'),
		(178, 'The requested position could not be found.');

/** Team players table **/
CREATE TABLE `sportsfixtures_team_user` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`team_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`sequence` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `sportsfixtures_team_user__team_user` (`team_id` ASC, `user_id` ASC),
	INDEX `fk_sportsfixtures_team_user__team_idx` (`team_id` ASC),
	INDEX `sportsfixtures_team_user__user` (`user_id` ASC),
	CONSTRAINT `fk_sportsfixtures_team_user__team`
	FOREIGN KEY (`team_id`)
	REFERENCES `sportsfixtures_team` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Team player positions **/
CREATE TABLE `sportsfixtures_team_position` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`team_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`position_id` INT NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `sportsfixtures_team_position__team_user_position` (`team_id` ASC, `user_id` ASC, `position_id` ASC),
	INDEX `fk_sportsfixtures_team_position__team_idx` (`team_id` ASC),
	INDEX `fk_sportsfixtures_team_position__position_idx` (`position_id` ASC),
	INDEX `sportsfixtures_team_position__user` (`user_id` ASC),
	CONSTRAINT `fk_sportsfixtures_team_position__team`
	FOREIGN KEY (`team_id`)
	REFERENCES `sportsfixtures_team` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE,
	FOREIGN KEY (`position_id`)
	REFERENCES `sportsfixtures_position` (`id`)
	ON DELETE CASCADE
	ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Tag cloud of enemy schools */
CREATE TABLE `sportsfixtures_school` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`name` varchar(255) DEFAULT NULL,
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1