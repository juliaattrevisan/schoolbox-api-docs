-- Evidence table creation

-- Evidence: a.k.a. "learning moment": proof that student `user_id` has learned
-- something.
-- * Text or a single file may be attached (body, hash)
-- * A discussion may be attached (discussion_thread_id)
CREATE TABLE `evidence`
(
    `id`                   INT UNSIGNED NOT NULL,
    `hash`                 VARCHAR(100) CHARSET ASCII NULL,
    `filename`             VARCHAR(120) NULL,
    `body`                 MEDIUMTEXT   NULL,
    `discussion_thread_id` INT UNSIGNED NULL,
    `user_id`              INT UNSIGNED NOT NULL,
    `created_by`           INT UNSIGNED NOT NULL,
    `created_at`           DATETIME     NOT NULL,
    `deleted_at`           DATETIME     NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_evidence_discussion_thread_id_idx` (`discussion_thread_id` ASC),
    INDEX `fk_evidence_user_id_idx`              (`user_id` ASC),
    INDEX `fk_evidence_created_by_idx`           (`created_by` ASC),
    INDEX `fk_evidence_hash_idx`                 (`hash` ASC),
    CONSTRAINT `fk_evidence_discussion_thread_id`
        FOREIGN KEY (`discussion_thread_id`)
            REFERENCES `discussion_thread` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_evidence_user_id`
        FOREIGN KEY (`user_id`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_evidence_created_by`
        FOREIGN KEY (`created_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_evidence_hash`
        FOREIGN KEY (`hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
) ENGINE = InnoDB;


-- Join a learning moment to a part of the curriculum.
CREATE TABLE `curriculum_evidence`
(
    `curriculum_node_id` INT NOT NULL,
    `evidence_id`        INT UNSIGNED NOT NULL,
    PRIMARY KEY (`curriculum_node_id`, `evidence_id`),
    CONSTRAINT `fk_curriculum_evidence_evidence_id`
        FOREIGN KEY (`evidence_id`)
            REFERENCES `evidence` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_curriculum_evidence_curriculum_node_id`
        FOREIGN KEY (`curriculum_node_id`)
            REFERENCES `curriculum_node` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
) ENGINE = InnoDB;


-- Available tags for learning moments.
-- It's likely these will be created only by superusers?
CREATE TABLE `lu_evidence_tag`
(
    `id`         INT UNSIGNED NOT NULL,
    `name`       VARCHAR(60)  NULL,
    `updated_by` INT          NULL,
    `created_at` DATETIME     NULL,
    `deleted_at` DATETIME     NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;


-- Which tags are applied to which learning moments?
CREATE TABLE `evidence_tag`
(
    `evidence_id` INT UNSIGNED NOT NULL,
    `tag_id`      INT UNSIGNED NOT NULL,
    PRIMARY KEY (`evidence_id`, `tag_id`),
    CONSTRAINT `fk_evidence_tag_evidence_id`
        FOREIGN KEY (`evidence_id`)
            REFERENCES `evidence` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    CONSTRAINT `fk_evidence_tag_tag_id`
        FOREIGN KEY (`tag_id`)
            REFERENCES `evidence_lu_tag` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
) ENGINE = InnoDB;
