-- Rename this config field so it's not synergetic-specific;
-- we'll be using it in suds

UPDATE `config`
SET `name` = 'staff_dir_category_restrict',
    `title` = 'Staff Directory Category Restriction',
    `description` = 'Insert the staff category codes that will be excluded from the Staff Directory.'
WHERE `name` = 'synergetic_staff_dir_restrict'
LIMIT 1;

-- Push down all the other configs in this area
-- Create a hole in config
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'External DB'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='staff_dir_category_restrict' LIMIT 1);

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'staff_dir_position_title_restrict',
    'Staff Directory Position Title Restriction',
    'Insert the staff position titles that will be excluded from the Staff Directory.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'External DB'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT sequence+1 FROM `config` c WHERE c.name = 'staff_dir_category_restrict')
);
