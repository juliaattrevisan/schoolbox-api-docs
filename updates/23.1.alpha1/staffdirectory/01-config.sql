-- Rename this config field so it's not synergetic-specific;
-- we'll be using it in suds

UPDATE `config`
SET `name` = 'staff_dir_category_restrict',
    `title` = 'Staff Directory Category Restriction',
    `description` = 'Insert the staff category codes that will be excluded from the Staff Directory.'
WHERE `name` = 'synergetic_staff_dir_restrict'
LIMIT 1;
