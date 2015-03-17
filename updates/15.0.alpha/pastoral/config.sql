INSERT INTO `config`(
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
    'external_staff_portal',
    '', -- 5 minutes
    'External Management Portal',
    'Provide the full URL to your SIS Management Portal (ie. Synweb).  This will be used to provide quicklinks into the system',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    'false',
    'false'
);
