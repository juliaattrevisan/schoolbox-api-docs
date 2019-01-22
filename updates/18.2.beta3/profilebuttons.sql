-- Deconflict table names

ALTER TABLE `profile_button_campus`
  RENAME TO  `profile_button_campus_availability` ;

ALTER TABLE `profile_button_role`
  RENAME TO  `profile_button_role_availability` ;

ALTER TABLE `profile_button_campus_availability`
  DROP INDEX `profile_button_campus_profile_button_id_fk` ,
  ADD INDEX `profile_button_campus_availability_profile_button_id_fk` (`profile_button_id` ASC);

ALTER TABLE `profile_button_role_availability`
  DROP INDEX `profile_button_role_profile_button_id_fk` ,
  ADD INDEX `profile_button_role_availability_profile_button_id_fk` (`profile_button_id` ASC);

-- Rename keys and indexes

ALTER TABLE `profile_button_campus_availability`
  DROP FOREIGN KEY `fk_profile_button_campus_campus_id`;
ALTER TABLE `profile_button_campus_availability`
  ADD CONSTRAINT `fk_profile_button_campus_availability_campus_id`
    FOREIGN KEY (`campus_id`)
      REFERENCES `luCampus` (`id`);

ALTER TABLE `profile_button_campus_availability`
  DROP FOREIGN KEY `profile_button_campus_profile_button_id_fk`;
ALTER TABLE `profile_button_campus_availability`
  ADD CONSTRAINT `profile_button_campus_availability_profile_button_id_fk`
    FOREIGN KEY (`profile_button_id`)
      REFERENCES `profile_button` (`id`);

ALTER TABLE `profile_button_role_availability`
  DROP INDEX `fk_profile_button_role_role_id_idx` ,
  ADD INDEX `fk_profile_button_role_availability_role_id_idx` (`role_id` ASC);

ALTER TABLE `profile_button_role_availability`
  DROP FOREIGN KEY `fk_profile_button_role_role_id`;
ALTER TABLE `profile_button_role_availability`
  ADD CONSTRAINT `fk_profile_button_role_availability_role_id`
    FOREIGN KEY (`role_id`)
      REFERENCES `groups` (`id`);

ALTER TABLE `profile_button_role_availability`
  DROP FOREIGN KEY `profile_button_role_profile_button_id_fk`;
ALTER TABLE `profile_button_role_availability`
  ADD CONSTRAINT `profile_button_role_availability_profile_button_id_fk`
    FOREIGN KEY (`profile_button_id`)
      REFERENCES `profile_button` (`id`);

ALTER TABLE `profile_button_campus_availability`
  DROP INDEX `fk_profile_button_campus_campus_id_idx` ,
  ADD INDEX `fk_profile_button_campus_availability_campus_id_idx` (`campus_id` ASC);

-- Set up Visibility tables

CREATE TABLE profile_button_campus_visibility
(
  id INT auto_increment
    PRIMARY KEY,
  profile_button_id INT NOT NULL,
  campus_id tinyint(2) unsigned NOT NULL,
  CONSTRAINT fk_profile_button_campus_visibility_campus_id
    FOREIGN KEY (campus_id) REFERENCES luCampus (id),
  CONSTRAINT profile_button_campus_visibility_profile_button_id_fk
    FOREIGN KEY (profile_button_id) REFERENCES profile_button (id)
)
  collate=utf8mb4_unicode_ci
;

CREATE index fk_profile_button_campus_visibility_campus_id_idx
  ON profile_button_campus_visibility (campus_id)
;

CREATE TABLE profile_button_role_visibility
(
  id INT auto_increment
    PRIMARY KEY,
  profile_button_id INT NOT NULL,
  role_id INT(11) unsigned NOT NULL,
  CONSTRAINT fk_profile_button_role_visibility_role_id
    FOREIGN KEY (role_id) REFERENCES groups (id),
  CONSTRAINT profile_button_role_visibility_profile_button_id_fk
    FOREIGN KEY (profile_button_id) REFERENCES profile_button (id)
)
  collate=utf8mb4_unicode_ci
;

CREATE index fk_profile_button_role_visibility_role_id_idx
  ON profile_button_role_visibility (role_id)
;