CREATE TABLE `pastoral_import_log`
(
    `id` INT NOT NULL AUTO_INCREMENT,
    `pastoral_id` INT,
    `import_id` INT,
    `user_id` INT,
    `student_name` VARCHAR(500),
    `creator_name` VARCHAR(500),
    `heading` VARCHAR(500),
    `result` VARCHAR(500),
    `created_at` DATETIME,

PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;
