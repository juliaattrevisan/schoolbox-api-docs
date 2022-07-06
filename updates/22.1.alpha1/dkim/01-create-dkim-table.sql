CREATE TABLE `email_dkim`
(
    `id`         int(10) unsigned                                                 NOT NULL AUTO_INCREMENT,
    `domain`     VARCHAR(1024) CHARACTER SET 'utf8mb4' COLLATE utf8mb4_unicode_ci NOT NULL,
    `public`     TEXT COLLATE ascii_general_ci,
    `private`    TEXT COLLATE ascii_general_ci,
    `created_at` DATETIME                                                         NOT NULL,
    `updated_at` DATETIME                                                         NOT NULL,
    `deleted_at` DATETIME                                                         NULL,
    PRIMARY KEY (`id`)

)
    DEFAULT CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
