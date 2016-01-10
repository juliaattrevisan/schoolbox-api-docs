INSERT IGNORE INTO `config` (
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
) VALUES (
    'external_sync_asctimetables_xml',
    '',
    'External Sync aSc Timetables XML Location',
    'External Sync aSc Timetables XML Location',
    1, -- text
    '',
    7, -- external DB
    'true',
    'true',
    'false'
);
