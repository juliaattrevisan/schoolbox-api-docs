/*
 Table structure for `sis_user_profile`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile`
(
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(64)  NOT NULL,
    `title`       VARCHAR(255) NULL,
    `firstname`   VARCHAR(255) NULL,
    `lastname`    VARCHAR(255) NULL,
    `created_at`  DATETIME     NOT NULL,
    `updated_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

CREATE UNIQUE INDEX idx_external_id
    ON sis_user_profile (external_id);

/*
 Table structure for `sis_user_profile_parent`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_parent`
(
    `sis_user_profile_id` INT UNSIGNED NOT NULL,
    `mobile`              VARCHAR(45)  NULL,
    `phone_home`          VARCHAR(45)  NULL,
    `phone_work`          VARCHAR(45)  NULL,
    `username`            VARCHAR(255) NULL,
    `email`               VARCHAR(255) NULL,
    `created_at`          DATETIME     NOT NULL,
    `updated_at`          DATETIME     NOT NULL,
    PRIMARY KEY (`sis_user_profile_id`),
    CONSTRAINT `fk1_sis_user_profile_parent`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

/*
  Table structure for `sis_user_profile_student`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_student`
(
    `sis_user_profile_id`           INT UNSIGNED  NOT NULL,
    `date_of_birth`                 DATE          NULL,
    `username`                      VARCHAR(255)  NULL,
    `email`                         VARCHAR(255)  NULL,
    `gender`                        VARCHAR(25)   NULL,
    `emergency_contact_name_1`      VARCHAR(255)  NULL,
    `emergency_contact_phone_1`     VARCHAR(255)  NULL,
    `emergency_contact_phone_alt_1` VARCHAR(255)  NULL,
    `emergency_contact_name_2`      VARCHAR(255)  NULL,
    `emergency_contact_phone_2`     VARCHAR(255)  NULL,
    `emergency_contact_phone_alt_2` VARCHAR(255)  NULL,
    `emergency_contact_name_3`      VARCHAR(255)  NULL,
    `emergency_contact_phone_3`     VARCHAR(255)  NULL,
    `emergency_contact_phone_alt_3` VARCHAR(255)  NULL,
    `campus_name`                   VARCHAR(255)  NULL,
    `head_of_house_id`              VARCHAR(64)   NULL,
    `head_of_house_name`            VARCHAR(255)  NULL,
    `house_name`                    VARCHAR(255)  NULL,
    `form_name`                     VARCHAR(255)  NULL,
    `form_teacher_id`               VARCHAR(64)   NULL,
    `form_teacher_name`             VARCHAR(255)  NULL,
    `tutor_group`                   VARCHAR(255)  NULL,
    `tutor_teacher_id`              VARCHAR(64)   NULL,
    `tutor_teacher_name`            VARCHAR(255)  NULL,
    `year_level`                    VARCHAR(255)  NULL,
    `year_level_coordinator_id`     VARCHAR(64)   NULL,
    `year_level_coordinator_name`   VARCHAR(255)  NULL,
    `mobile`                        VARCHAR(45)   NULL,
    `studies_code`                  VARCHAR(45)   NULL,
    `alert_medical`                 VARCHAR(1024) NULL,
    `alert_comment`                 VARCHAR(1024) NULL,
    `alert_message`                 VARCHAR(1024) NULL,
    `alert_legal`                   VARCHAR(1024) NULL,
    `enrolment_date`                DATE          NULL,
    `leaving_date`                  DATE          NULL,
    `ib`                            TINYINT(1)    NULL,
    `individual_learning_plans`     TINYINT(1)    NULL,
    `esl`                           TINYINT(1)    NULL,
    `special_needs`                 TINYINT(1)    NULL,
    `boarding_status`               TINYINT(1)    NULL,
    `created_at`                    DATETIME      NOT NULL,
    `updated_at`                    DATETIME      NOT NULL,
    PRIMARY KEY (`sis_user_profile_id`),
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
    `username`             VARCHAR(255) NULL,
    `email`                VARCHAR(255) NULL,
    `category`             VARCHAR(255) NULL,
    `position_title`       VARCHAR(255) NULL,
    `department`           VARCHAR(255) NULL,
    `phone`                VARCHAR(45)  NULL,
    `room`                 VARCHAR(255) NULL,
    `building`             VARCHAR(255) NULL,
    `campus_name`          VARCHAR(255) NULL,
    `created_at`           DATETIME     NOT NULL,
    `updated_at`           DATETIME     NOT NULL,
    PRIMARY KEY (`sis_user_profile_id`),
    CONSTRAINT `fk1_sis_user_profile_staff`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

/*
  Table structure for `sis_profile_relation`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_relation`
(
    `child_profile_id`  INT UNSIGNED NOT NULL,
    `user_profile_id`   INT UNSIGNED NOT NULL,
    `type`              VARCHAR(55)  NOT NULL,
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
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
