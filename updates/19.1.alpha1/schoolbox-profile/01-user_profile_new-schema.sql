/*
 Table structure for `sis_user_profile`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile`
(
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(64)  NOT NULL,
    `gender`      VARCHAR(25)  Null,
    `title`       VARCHAR(255) NULL,
    `firstname`   VARCHAR(255) NULL,
    `lastname`    VARCHAR(255) NULL,
    `email`       VARCHAR(255) NULL,
    'created_at'  DATE         NULL,
    'updated_at'  DATE         NULL,
    'deleted_at'  DATE         NULL,
    PRIMARY KEY (`id`)
)
    ENGINE=InnoDB DEFAULT
    CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;

/*
 Table structure for `sis_user_profile_parent`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_parent`
(
    `sis_user_profile_id` INT UNSIGNED NOT NULL,
    `mobile`              VARCHAR(45)  NULL,
    `phone_home`          VARCHAR(45)  NULL,
    `phone_work`          VARCHAR(45)  NULL,
    `email`               VARCHAR(255) NULL,
    'created_at'          DATE         NULL,
    'updated_at'          DATE         NULL,
    'deleted_at'          DATE         NULL,
    INDEX `id_idx` (`sis_user_profile_id` ASC),
    PRIMARY KEY (`sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_parent`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE=InnoDB DEFAULT
    CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;

/*
  Table structure for `sis_user_profile_student`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_student`
(
    `sis_user_profile_id`         INT UNSIGNED  NOT NULL,
    `date_of_birth`               DATE          NULL,
    `ib`                          TINYINT(1)    NULL,
    `individual_learning_plans`   TINYINT(1)    NULL,
    `esl`                         TINYINT(1)    NULL,
    `head_of_house_id`            VARCHAR(64)   NULL,
    `head_of_house_name`          VARCHAR(255)  NULL,
    `house_name`                  VARCHAR(255)  NULL,
    `house_description`           VARCHAR(255)  NULL,
    `form_name`                   VARCHAR(255)  NULL,
    `form_description`            VARCHAR(255)  NULL,
    `teacher_form_id`             VARCHAR(64)   NULL,
    `teacher_form_name`           VARCHAR(255)  NULL,
    `tutor_id`                    VARCHAR(64)   NULL,
    `tutor_name`                  VARCHAR(255)  NULL,
    `tutor_group`                 VARCHAR(255)  NULL,
    `tutor_description`           VARCHAR(255)  NULL,
    `year_level`                  VARCHAR(255)  NULL,
    `year_level_coordinator_id`   VARCHAR(64)   NULL,
    `year_level_coordinator_name` VARCHAR(255)  NULL,
    `mobile`                      VARCHAR(45)   NULL,
    `special_needs`               TINYINT(1)    NULL,
    `studies_code`                VARCHAR(45)   NULL,
    `boarding_status`             TINYINT(1)    NULL,
    `enrolment_date`              DATE          NULL,
    `leaving_date`                DATE          NULL,
    'created_at'                  DATE          NULL,
    'updated_at'                  DATE          NULL,
    'deleted_at'                  DATE          NULL,
    INDEX `id_idx` (`sis_user_profile_id` ASC),
    PRIMARY KEY (`sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_student`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

/*
  Table structure for `sis_user_profile_staff`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_staff`
(
    `sis_user_profile_id`  INT UNSIGNED NOT NULL,
    `category`             VARCHAR(255) NULL,
    `position_title`       VARCHAR(255) NULL,
    `position_description` VARCHAR(500) NULL,
    `department`           VARCHAR(255) NULL,
    `phone_work`           VARCHAR(45)  NULL,
    `room`                 VARCHAR(255) NULL,
    `building`             VARCHAR(255) NULL,
    `work_email`           VARCHAR(255)  NULL,
    `campus_name`          VARCHAR(255) NULL,
    'created_at'           DATE         NULL,
    'updated_at'           DATE         NULL,
    'deleted_at'           DATE         NULL,
    INDEX `id_idx` (`sis_user_profile_id` ASC),
    PRIMARY KEY (`sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_staff`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE=InnoDB DEFAULT
    CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;

/*
  Table structure for `sis_user_profile_alerts`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_alerts` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sis_user_profile_id`  INT UNSIGNED NOT NULL,
    `type`                 ENUM('medical', 'legal', 'general') NULL,
    `body`                 TEXT         NULL,
    'created_at'           DATE         NULL,
    'updated_at'           DATE         NULL,
    'deleted_at'           DATE         NULL,
    PRIMARY KEY (`id`, `sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_alerts`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION)
    ENGINE=InnoDB DEFAULT
    CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;

/*
  Table structure for `sis_profile_relation`
*/
CREATE TABLE IF NOT EXISTS `sis_profile_relation`
(
    `child_profile_id`  INT UNSIGNED NOT NULL,
    `user_profile_id`   INT UNSIGNED NOT NULL,
    `emergency_contact` TINYINT(1)   NOT NULL,
    `type`              VARCHAR(45)  NOT NULL,
    INDEX `fk_sis_profile_relation_1_idx` (`child_profile_id` ASC),
    INDEX `fk_sis_profile_relation_2_idx` (`user_profile_id` ASC),
    CONSTRAINT `fk_sis_profile_relation_1`
        FOREIGN KEY (`child_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_sis_profile_relation_2`
        FOREIGN KEY (`user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE=InnoDB DEFAULT
    CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
