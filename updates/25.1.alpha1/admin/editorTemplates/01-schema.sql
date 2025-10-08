-- A list of editor template categories
CREATE TABLE `editor_template_category` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Insert the initial categories into the editor_template_category table
INSERT INTO
    `editor_template_category` (`name`)
VALUES ('badge-evidence'),
    ('calendar-events'),
    ('email'),
    ('feedback'),
    ('homepage'),
    ('learning-activity'),
    ('learning-moments'),
    ('news');

-- A list of editor templates that can be used to create content, each linked to a category
CREATE TABLE `editor_template` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `body` mediumtext NOT NULL,
    `created_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `updated_by` INT UNSIGNED NOT NULL,
    `deleted_at` DATETIME NULL,
    `deleted_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    INDEX `category` (`category_id` ASC),
    INDEX `creator` (`created_by` ASC),
    INDEX `updater` (`updated_by` ASC),
    INDEX `deleter` (`deleted_by` ASC),
    CONSTRAINT `fk_editor_template_category` FOREIGN KEY (`category_id`) REFERENCES `editor_template_category` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_editor_template_creator` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_editor_template_updater` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_editor_template_deleter` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
