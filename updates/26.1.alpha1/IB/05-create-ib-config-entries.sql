-- Create IB configuration entries in the config table
-- This migration adds the school name and school code configuration entries for IB

INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES
(
    'ib_school_name',
    '',
    'IB School Name',
    'Enter the school\'s name as registered with the International Baccalaureate.',
    1, -- TEXT INPUT field type
    NULL,
    11, -- TYPE_MODULES
    'true',
    'true',
    'false',
    'settings',
    1
),
(
    'ib_school_code',
    '',
    'IB School Code',
    'Enter the school\'s code as provided by the International Baccalaureate.',
    1, -- TEXT INPUT field type
    NULL,
    11, -- TYPE_MODULES
    'true',
    'true',
    'false',
    'settings',
    2
);
