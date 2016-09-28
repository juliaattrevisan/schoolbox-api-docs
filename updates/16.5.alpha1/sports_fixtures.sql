/* Add Service permission for Sports Admin
 */
INSERT INTO `luServices` (`id`, `name`) VALUES (10, 'Sports Administrator') ON DUPLICATE KEY UPDATE `id` = `id`;

/* Sport Table
 */
CREATE TABLE `sportsfixtures_sport` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`name` VARCHAR(100) NULL COMMENT '',
	`url` VARCHAR(2048) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

/* Season Table */
CREATE TABLE `sportsfixtures_season` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`start` DATE NOT NULL,
	`end` DATE NOT NULL,
	`archived_at` DATETIME NULL DEFAULT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Division Table
 */
CREATE TABLE `sportsfixtures_division` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`sport_id` INT NULL COMMENT '',
	`season_id` INT NOT NULL,
	`name` VARCHAR(255) NULL COMMENT '',
	`url` VARCHAR(2048) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`sport_id`)
	REFERENCES `sportsfixtures_sport`(`id`)
		ON UPDATE RESTRICT
		ON DELETE RESTRICT,
	FOREIGN KEY (`season_id`)
	REFERENCES `sportsfixtures_season`(`id`)
		ON UPDATE RESTRICT
		ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Team Table
 */
CREATE TABLE `sportsfixtures_team` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`division_id` INT NULL COMMENT '',
	`name` VARCHAR(255) NULL COMMENT '',
	`url` VARCHAR(2048) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`division_id`)
	REFERENCES `sportsfixtures_division`(`id`)
		ON UPDATE RESTRICT
		ON DELETE RESTRICT
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
		ON UPDATE RESTRICT
		ON DELETE RESTRICT
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
		ON DELETE RESTRICT
		ON UPDATE RESTRICT
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
	`address` VARCHAR(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)  COMMENT ''
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Match Table
 */
CREATE TABLE `sportsfixtures_match` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`division_id` INT NOT NULL,
	`season_id` INT NOT NULL,
	`venue_id` INT NOT NULL,
	`home_team_id` INT,
	`away_team_id` INT NULL,
	`home_school_id` INT NULL,
	`away_school_id` INT NULL,
	`name` VARCHAR(255) NULL COMMENT '',
	`notes` TEXT NULL COMMENT '',
	`start_at` DATETIME NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`division_id`)
		REFERENCES `sportsfixtures_division`(`id`)
			ON UPDATE RESTRICT
			ON DELETE RESTRICT,
	FOREIGN KEY (`season_id`)
	  REFERENCES `sportsfixtures_season`(`id`)
		  ON UPDATE RESTRICT
		  ON DELETE RESTRICT,
	FOREIGN KEY (`venue_id`)
		REFERENCES `sportsfixtures_venue`(`id`)
			ON UPDATE RESTRICT
			ON DELETE RESTRICT,
	FOREIGN KEY (`home_team_id`)
		REFERENCES `sportsfixtures_team`(`id`)
			ON UPDATE RESTRICT
			ON DELETE RESTRICT,
	FOREIGN KEY (`away_team_id`)
		REFERENCES `sportsfixtures_team`(`id`)
			ON UPDATE RESTRICT
			ON DELETE RESTRICT
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
			ON UPDATE RESTRICT
			ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Match Status Table (add before sportsfixtures_result)
 */
CREATE TABLE `sportsfixtures_status` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`name` varchar(255) NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

INSERT INTO `sportsfixtures_status` VALUES
	(null,'Win',NOW(),NOW(),null),
	(null,'Loss',NOW(),NOW(),null),
	(null,'Draw',NOW(),NOW(),null),
	(null,'Cancelled',NOW(),NOW(),null);

/* Match Result Table
 */
CREATE TABLE `sportsfixtures_result` (
	`id` INT NOT NULL AUTO_INCREMENT COMMENT '',
	`match_id` INT NOT NULL,
	`team_id` INT NOT NULL,
	`status_id` INT NULL,
	`description` TEXT NULL COMMENT '',
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`match_id`)
		REFERENCES `sportsfixtures_match`(`id`)
			ON UPDATE RESTRICT
			ON DELETE RESTRICT,
	FOREIGN KEY (`status_id`)
	REFERENCES `sportsfixtures_status`(`id`)
		ON UPDATE RESTRICT
		ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Error table entries */
INSERT INTO `error` VALUES
    (173, 'The requested sport could not be found.'),
    (174, 'The requested division could not be found.'),
    (175, 'The requested team could not be found.'),
    (176, 'The requested venue could not be found.'),
		(177, 'The requested match could not be found.'),
		(178, 'The requested position could not be found.'),
		(179, 'The requested status could not be found.'),
		(180, 'That which you are looking for could not be found.'),
		(181, 'The requested season could not be found.'),
		(182, 'The requested item has been archived.');

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
		ON DELETE RESTRICT
		ON UPDATE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Team Participant table **/
CREATE TABLE `sportsfixtures_team_participantuser`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`match_id` INT(11),
	`team_id` INT(11),
	`user_id` INT(11),
	`sequence` INT(11),
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`deleted_at` DATETIME,
PRIMARY KEY (`id`)
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
		ON DELETE RESTRICT
		ON UPDATE RESTRICT,
	FOREIGN KEY (`position_id`)
	REFERENCES `sportsfixtures_position` (`id`)
	ON DELETE RESTRICT
	ON UPDATE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Team Participant Positions */
CREATE TABLE `sportsfixtures_team_participantposition`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`match_id` INT(11) NOT NULL,
	`team_id` INT(11) NOT NULL,
	`user_id` INT(11) NOT NULL,
	`name` VARCHAR(255),
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`deleted_at` DATETIME, PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

/* Tag cloud of enemy schools */
CREATE TABLE `sportsfixtures_school` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`name` varchar(255) DEFAULT NULL,
	`created_at` DATETIME NULL COMMENT '',
	`updated_at` DATETIME NULL COMMENT '',
	`deleted_at` DATETIME NULL COMMENT '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Coaches table **/
CREATE TABLE `sportsfixtures_coach` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`user_id` INT NULL DEFAULT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	INDEX `sportsfixtures_coach__name` (`name` ASC),
	INDEX `sportsfixtures_coach__user` (`user_id` ASC)
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Team Participant Coach */
CREATE TABLE `sportsfixtures_team_participantcoach`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`match_id` INT(11) NOT NULL,
	`team_id` INT(11) NOT NULL,
	`user_id` INT(11) NULL,
	`name` VARCHAR(255) NOT NULL,
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`deleted_at` DATETIME, PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

/** Coaches/teams M2M join table **/
CREATE TABLE `sportsfixtures_team_coach` (
	`team_id` INT NOT NULL,
	`coach_id` INT NOT NULL,
	INDEX `fk_sportsfixtures_team_coach_1_idx` (`team_id` ASC),
	INDEX `fk_sportsfixtures_team_coach_2_idx` (`coach_id` ASC),
	CONSTRAINT `fk_sportsfixtures_team_coach__team`
	FOREIGN KEY (`team_id`)
	REFERENCES `sportsfixtures_team` (`id`)
		ON DELETE RESTRICT
		ON UPDATE RESTRICT,
	CONSTRAINT `fk_sportsfixtures_team_coach__coach`
	FOREIGN KEY (`coach_id`)
	REFERENCES `sportsfixtures_coach` (`id`)
		ON DELETE RESTRICT
		ON UPDATE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8;


INSERT INTO `msg_namespace` (`slug`, `label`) VALUES ('sports_fixtures', 'Sports Fixtures');

SET @sportns = (SELECT id FROM msg_namespace WHERE slug = 'sports_fixtures');

INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'removed', 'student', 'a student is removed from a team');
INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'removed', 'parent', 'a student is removed from a team');

INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'created', 'student', 'a match is upcoming');
INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'updated', 'student', 'an upcoming match has been updated');

INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'created', 'parent', 'a match is upcoming');
INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'updated', 'parent', 'an upcoming match has been updated');

INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'resultCreated', 'student', 'a match result has been entered');
INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'resultUpdated', 'student', 'a match result has been updated');

INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'resultCreated', 'parent', 'a match result has been entered');
INSERT INTO `msg_source` (namespace, `key`, role_type, description ) VALUES (@sportns, 'resultUpdated', 'parent', 'a match result has been updated');

INSERT INTO `msg_route_perm`
SELECT s.id src_id, g.id role_id, 'MU,IN,MO,EM', 'IN,MO' FROM msg_source s
JOIN groups g ON g.group_type = s.role_type
WHERE s.namespace = @sportns;

INSERT INTO `component_definition` (name, class, description, module, component_category, visible, multiple)
VALUES ('Sport Fixtures', 'Schoolbox\\SportsFixtures\\Component\\Homepage\\Controller', 'Shows sports fixtures', 'homepage', 1, 'true', 'true');

INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Homepage\\Controller'), 'collapse', '0', 'Collapse Content', null);
INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Homepage\\Controller'), 'team', '0', 'Team To Display', null);
INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Homepage\\Controller'), 'componentname', 'Sports Fixtures', 'Heading', null);
INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Homepage\\Controller'), 'showHistorical', '1', 'Show Historical', 'Whether the component should display matches played in the past');

INSERT INTO `component_definition` (name, class, description, module, component_category, visible, multiple)
VALUES ('Sport Fixtures', 'Schoolbox\\SportsFixtures\\Component\\Dashboard\\Controller', 'Shows sports fixtures', 'dashboard', null, 'true', 'false');

INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Dashboard\\Controller'), 'collapse', '0', 'Collapse Content', null);
INSERT INTO `component_config_definition` (component_definition_id, `key`, default_value, label, description)
VALUES ((SELECT id from `component_definition` WHERE class='Schoolbox\\SportsFixtures\\Component\\Dashboard\\Controller'), 'componentname', 'Sports Fixtures', 'Heading', null);

CREATE TABLE sportsfixtures_import
(
	id INT(11) NOT NULL AUTO_INCREMENT,
	status VARCHAR(50) DEFAULT 'Pending',
	owner_id INT,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE sportsfixtures_import_errors
(
	id INT(11) NOT NULL AUTO_INCREMENT,
	import_id INT,
	filename VARCHAR(255),
	hash VARCHAR(40),
	type VARCHAR(40),
	line INT,
	`column` INT,
	result TEXT,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET=utf8;

-- Create a stored procedure to create the message route permissions

DELIMITER ;
DROP PROCEDURE IF EXISTS create_route_perms;

DELIMITER //
CREATE PROCEDURE create_route_perms(IN source INT, IN grouptype VARCHAR(255))
	BEGIN

		DECLARE role_id INT DEFAULT 0;
		DECLARE cursor_finished INT DEFAULT 0;
		DECLARE cursor_roles CURSOR FOR SELECT `id` FROM `groups` WHERE `group_type` = grouptype;

		DECLARE CONTINUE HANDLER FOR NOT FOUND SET cursor_finished = 1;

		OPEN cursor_roles;

		role_loop: LOOP

			FETCH cursor_roles INTO role_id;

			IF cursor_finished = 1 THEN
				LEAVE role_loop;
			END IF;

			INSERT INTO msg_route_perm (msg_source_id, role_id, mask, role_default)
			VALUES (source, role_id, 'MU,DI,IN,MO,EM', 'IN');

		END LOOP;

	END
//

DELIMITER ;

-- Delete any previous created pastoral notification stuff

DELETE FROM msg_route_perm WHERE msg_source_id in (SELECT `id` FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'sports_fixtures_import'));
DELETE FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'sports_fixtures_import');
DELETE FROM msg_namespace WHERE slug = 'sports_fixtures_import';

-- Create notification namespace and get its ID

INSERT INTO msg_namespace (slug, label) VALUES ('sports_fixtures_import', 'Sports Fixture Import');
SET @namespace = LAST_INSERT_ID();

-- Staff create message source

INSERT INTO msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'created', 'staff', 'a sports fixtures import has started.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'staff');

-- Staff update message source

INSERT INTO msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'updated', 'staff', 'a sports fixtures import has progressed.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'staff');

-- Get rid of the stored procedure after everything created as it really serves no purpose anymore

DROP PROCEDURE IF EXISTS create_route_perms;
