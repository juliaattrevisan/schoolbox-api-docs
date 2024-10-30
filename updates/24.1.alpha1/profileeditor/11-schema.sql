-- Back up ol' boy

ALTER TABLE `profile_button`
    RENAME TO  `_profile_button` ;

-- Recreate it anew
-- Add support for categorisation of buttons
-- Add support for knowing the role type for a button

CREATE TABLE `profile_button` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sort_order` int(11) DEFAULT NULL,
    `title` varchar(255) DEFAULT NULL,
    `text` varchar(255) DEFAULT NULL,
    `icon` varchar(255) DEFAULT NULL,
    `url` varchar(2048) DEFAULT NULL,
    `css` varchar(255) DEFAULT NULL,
    `target` varchar(255) DEFAULT NULL,
    `type` enum('system','custom') NOT NULL DEFAULT 'custom',
    `roletype` enum('student','staff','parent') NOT NULL,
    `deleted_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Put back the old rows here, cos we're gonna work on them next

INSERT INTO `profile_button` (id, sort_order, title, text, icon, url, css, target, deleted_at)
SELECT * FROM `_profile_button` WHERE deleted_at IS NULL;
