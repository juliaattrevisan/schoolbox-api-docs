CREATE TABLE pastoral_audience (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type_id` int(11) DEFAULT NULL,
    `subtype_id` int(11) DEFAULT NULL,
    `access`  int(11) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `role_id` int(11) DEFAULT NULL,
    `group_id` int(11) DEFAULT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`)
);
