CREATE TABLE `term`(
    `id` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `code` VARCHAR(16) NOT NULL,
    `start` DATE NOT NULL,
    `finish` DATE NOT NULL,
    `archived` BOOL NOT NULL DEFAULT FALSE,
    UNIQUE INDEX (`title`),
    UNIQUE INDEX (`code`),
    INDEX (`start`),
    INDEX (`finish`)
) ENGINE=INNODB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
