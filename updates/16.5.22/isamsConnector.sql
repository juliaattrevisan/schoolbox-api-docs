UPDATE `config`
SET `options` = CONCAT(`options`, ':isams')
WHERE `name` = 'external_type'
AND `options` NOT LIKE '%isams%'
LIMIT 1;

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
    `required`
)
VALUES (
    'external_isams_default_day_id',
    '50',
    'Isams Default Day ID',
    'This is the value of the DayID column from the V_TimetableStructure view that represents the first day of the school week',
    1, -- input
    '',
    7, -- external DB
    'true',
    (IF ((SELECT COUNT(*) FROM `config` c2 WHERE c2.`name` = 'external_type' AND c2.`value` = 'isams' LIMIT 1), 'false', 'true')),
    'false'
);
