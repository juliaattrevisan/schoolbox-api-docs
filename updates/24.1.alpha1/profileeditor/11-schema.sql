-- Back up ol' boy

CREATE TABLE `_profile_button` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sort_order` int(11) DEFAULT NULL,
    `title` varchar(255) DEFAULT NULL,
    `text` varchar(255) DEFAULT NULL,
    `icon` varchar(255) DEFAULT NULL,
    `url` varchar(2048) DEFAULT NULL,
    `css` varchar(255) DEFAULT NULL,
    `target` varchar(255) DEFAULT NULL,
    `deleted_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `_profile_button` (id, sort_order, title, text, icon, url, css, target, deleted_at)
SELECT * FROM `profile_button` WHERE deleted_at IS NULL;

-- Augment the current table with two new columns
-- Add support for categorisation of buttons
-- Add support for knowing the role type for a button

-- roletype is nullable for now, to ease migration
-- I don't want default values to cloud what I'm seeing here

ALTER TABLE `profile_button`
    ADD COLUMN `roletype` ENUM('student', 'staff', 'parent') NULL DEFAULT NULL AFTER `id`,
    ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT 'custom' AFTER `roletype`;
