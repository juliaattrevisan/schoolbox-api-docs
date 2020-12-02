-- -----------------------------------------------------
-- Table `badge_category`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `badge_category`(
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL,
    `updated_at` DATETIME     NOT NULL,
    `deleted_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
CONSTRAINT `fk_achievement_badge_category_created_by_id`
    FOREIGN KEY (`created_by`)
    REFERENCES `user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table `badge`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `badge` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `image_hash` VARCHAR(100) CHARACTER SET ascii NULL,
    `image_path` VARCHAR(255),
    `category_id` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `updated_by` INT UNSIGNED NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
INDEX `fk_achievement_badge_category_id_idx` (`category_id` ASC) ,
INDEX `fk_achievement_badge_created_by_id_idx` (`created_by` ASC) ,
INDEX `fk_achievement_badge_updated_by_idx` (`updated_by` ASC) ,
CONSTRAINT `fk_achievement_badge_category_id`
    FOREIGN KEY (`category_id`)
    REFERENCES `badge_category` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
CONSTRAINT `fk_achievement_badge_created_by_id`
    FOREIGN KEY (`created_by`)
    REFERENCES `user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
CONSTRAINT `fk_achievement_badge_image_hash`
    FOREIGN KEY (`image_hash`)
    REFERENCES `storage` (`hash`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
CONSTRAINT `fk_achievement_badge_updated_by_id`
    FOREIGN KEY (`updated_by`)
    REFERENCES `user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table `curriculum_badge`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `curriculum_badge` (
    `badge_id` INT UNSIGNED NOT NULL,
    `curriculum_node_id` INT NOT NULL,
INDEX `fk_curriculum_achievement_curriculum_node_id` (`curriculum_node_id` ASC),
UNIQUE INDEX `curriculum_badge_UNIQUE` (`badge_id` ASC, `curriculum_node_id` ASC),
INDEX `fk_curriculum_achievement_badge_id` (`badge_id` ASC),
CONSTRAINT `fk_curriculum_achievement_badge_id`
  FOREIGN KEY (`badge_id`)
      REFERENCES `badge` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
CONSTRAINT `fk_curriculum_achievement_curriculum_id`
  FOREIGN KEY (`curriculum_node_id`)
      REFERENCES `curriculum_node` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table `badge_user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `badge_user` (
    `recipient_id` INT UNSIGNED NOT NULL,
    `badge_id` INT UNSIGNED NOT NULL,
    `status` ENUM('goal', 'achievement') NOT NULL,
    `awarded_by` INT UNSIGNED NOT NULL,
    `awarded_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    INDEX `fk_badge_user_recipient_id_idx` (`recipient_id` ASC),
    INDEX `fk_badge_user_badge_id_idx` (`badge_id` ASC),
    INDEX `fk_badge_user_awarded_by_id_idx` (`awarded_by` ASC),
    CONSTRAINT `fk_badge_user_recipient_id`
        FOREIGN KEY (`recipient_id`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_badge_user_badge_id`
        FOREIGN KEY (`badge_id`)
            REFERENCES `badge` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_badge_user_awarded_by_id`
        FOREIGN KEY (`awarded_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- Badge Category sequence
ALTER TABLE `badge_category`
    ADD COLUMN `sequence` INT(10) UNSIGNED NOT NULL AFTER `name`;
