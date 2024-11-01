-- Back up ol' boy

CREATE TABLE `_profile_button` AS SELECT * FROM profile_button;

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
