CREATE TABLE `term`(
    `title` VARCHAR(100) NOT NULL,
    `start` DATE NOT NULL,
    `finish` DATE NOT NULL,
    `archived` BOOL NOT NULL,
    UNIQUE INDEX (`title`),
    INDEX (`start`),
    INDEX (`finish`)
) ENGINE=INNODB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
