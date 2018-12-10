CREATE TABLE pastoral_audience (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type_id` int(11) DEFAULT NULL,
    `subtype_id` int(11) DEFAULT NULL,
    `access`  int(11) NOT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `role_id` INT(11) UNSIGNED DEFAULT NULL,
    `group_id` int(11) DEFAULT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT pastoral_audience_type_id    FOREIGN KEY (`type_id`)    REFERENCES lu_pastoral_type (`id`)    ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT pastoral_audience_subtype_id FOREIGN KEY (`subtype_id`) REFERENCES lu_pastoral_subtype (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT pastoral_audience_user_id    FOREIGN KEY (`user_id`)    REFERENCES user (`id`)                ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT pastoral_audience_role_id    FOREIGN KEY (`role_id`)    REFERENCES `groups` (`id`)            ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT pastoral_audience_group_id   FOREIGN KEY (`group_id`)   REFERENCES pastoral_group (`id`)      ON DELETE RESTRICT ON UPDATE RESTRICT
);
