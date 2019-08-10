/*
 Table structure for `sis_user_profile`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile`
(
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(64)  NOT NULL,
    `gender`      VARCHAR(25)  Null,
    `title`       VARCHAR(45)  NULL,
    `firstname`   VARCHAR(250) NULL,
    `lastname`    VARCHAR(250) NULL,
    `email`       VARCHAR(60)  NULL,
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8;

/*
 Table structure for `sis_user_profile_parent`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_parent`
(
    `sis_user_profile_id` INT UNSIGNED NOT NULL,
    `mobile`              VARCHAR(45)  NULL,
    `phone_home`          VARCHAR(45)  NULL,
    `phone_work`          VARCHAR(45)  NULL,
    `email`               VARCHAR(60)  NULL,
    INDEX `id_idx` (`sis_user_profile_id` ASC),
    PRIMARY KEY (`sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_parent`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8;

/*
  Table structure for `sis_user_profile_student`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_student`
(
    `sis_user_profile_id`       INT UNSIGNED  NOT NULL,
    `date_of_birth`             VARCHAR(45)   NULL,
    `ib`                        TINYINT(1)    NULL,
    `individual_learning_plans` TINYINT(1)    NULL,
    `esl`                       TINYINT(1)    NULL,
    `head_of_house_id`          VARCHAR(64)   NULL,
    `head_of_house`             VARCHAR(250)  NULL,
    `house`                     VARCHAR(250)  NULL,
    `house_description`         VARCHAR(500)  NULL,
    `form_name`                 VARCHAR(250)  NULL,
    `form_description`          VARCHAR(250)  NULL,
    `teacher_form_id`           VARCHAR(64)   NULL,
    `teacher_form_name`         VARCHAR(250)  NULL,
    `tutor_id`                  VARCHAR(64)   NULL,
    `tutor_name`                VARCHAR(250)  NULL,
    `tutor_group`               VARCHAR(250)  NULL,
    `tutor_description`         VARCHAR(250)  NULL,
    `year_level`                VARCHAR(255)  NULL,
    `year_level_coord_id`       VARCHAR(64)   NULL,
    `year_level_coordinator`    VARCHAR(250)  NULL,
    `medical_alert`             VARCHAR(1000) NULL,
    `alert_message`             VARCHAR(500)  NULL,
    `mobile`                    VARCHAR(45)   NULL,
    `special_needs`             TINYINT(1)    NULL,
    `studies_code`              VARCHAR(45)   NULL,
    `court_order_type`          VARCHAR(250)  NULL,
    `boarding_status`           TINYINT(1)    NULL,
    `comment`                   VARCHAR(1000) NULL,
    `enrolment_date`            VARCHAR(45)   NULL,
    `leaving_date`              VARCHAR(45)   NULL,
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
    DEFAULT CHARSET = utf8;

/*
  Table structure for `sis_user_profile_staff`
*/
CREATE TABLE IF NOT EXISTS `sis_user_profile_staff`
(
    `sis_user_profile_id`  INT UNSIGNED NOT NULL,
    `category`             VARCHAR(250) NULL,
    `position_title`       VARCHAR(250) NULL,
    `position_description` VARCHAR(500) NULL,
    `department`           VARCHAR(250) NULL,
    `phone`                VARCHAR(45)  NULL,
    `room`                 VARCHAR(250) NULL,
    `building`             VARCHAR(250) NULL,
    `work_email`           VARCHAR(60)  NULL,
    `campus`               VARCHAR(250) NULL,
    INDEX `id_idx` (`sis_user_profile_id` ASC),
    PRIMARY KEY (`sis_user_profile_id`),
    UNIQUE INDEX `sis_user_profile_id_UNIQUE` (`sis_user_profile_id` ASC),
    CONSTRAINT `fk1_sis_user_profile_staff`
        FOREIGN KEY (`sis_user_profile_id`)
            REFERENCES `sis_user_profile` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8;

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
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8;
