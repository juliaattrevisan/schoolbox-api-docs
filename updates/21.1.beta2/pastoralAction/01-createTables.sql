-- Look up table for pastoral actions
CREATE TABLE `lu_pastoral_action`
(
    `id`            int(11)                                 NOT NULL AUTO_INCREMENT,
    `name`          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `external_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `sequence`      int(11) unsigned                        NOT NULL,
    `created_at`    datetime                                NOT NULL,
    `updated_at`    datetime                                NOT NULL,
    `deleted_at`    datetime                               DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- Table to connect pastoral types/subtypes to pastoral actions
CREATE TABLE `pastoral_type_action`
(
    `type_id`    int(11),
    `subtype_id` int(11),
    `action_id`  int(11) NOT NULL,
    KEY `fk_type_id_idx` (`type_id`),
    KEY `fk_subtype_id_idx` (`subtype_id`),
    KEY `fk_action_id_idx` (`action_id`),
    CONSTRAINT `fk_pastoral_type_action_type_id` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_pastoral_type_action_subtype_id` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_pastoral_type_action_action_id` FOREIGN KEY (`action_id`) REFERENCES `lu_pastoral_action` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- Table to connect pastoral records to pastoral actions
CREATE TABLE `pastoral_record_action`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `pastoral_id`  int(11),
    `action_id`    int(11) NOT NULL,
    `external_id`  int(11), -- PastoralCareActionsSeq
    `completed_by` int(10) unsigned,
    `completed_at` datetime,
    PRIMARY KEY (`id`),
    KEY `fk_pastoral_id_idx` (`pastoral_id`),
    KEY `fk_action_id_idx` (`action_id`),
    CONSTRAINT `fk_pastoral_record_action_pastoral_id` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_pastoral_record_action_action_id` FOREIGN KEY (`action_id`) REFERENCES `lu_pastoral_action` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_pastoral_record_action_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;
