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
    'external_synergetic_student_contact_types',
    'SC1,SC2',
    'Synergetic Student Contact Types',
    'Sets the contact types that sync scripts and UPS for parents recognise as the guardians of a student. (Most student guardian queries use "SC1,SC2,SC3".)',
    1, -- input
    '',
    7, -- external DB
    'true',
    (IF ((SELECT COUNT(*) FROM `config` c2 WHERE c2.`name` = 'external_type' AND c2.`value` = 'synergetic' LIMIT 1), 'false', 'true')),
    'false'
);

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
    'external_synergetic_student_relationships',
    'SN,STSN,DR,STDR,WD,GRASN,GRADR,FOCD,HOPT,HOCD,HS,HOMES,HSS',
    'Synergetic Student Relationships',
    'Sets the relationships that sync scripts and UPS for parents recognise as the guardians of a student. (Most student guardian queries use "SN,STSN,DR,STDR,WD,GRASN,GRADR,FOCD,HOPT,HOCD,HS,HOMES,HSS".)',
    1, -- input
    '',
    7, -- external DB
    'true',
    (IF ((SELECT COUNT(*) FROM `config` c2 WHERE c2.`name` = 'external_type' AND c2.`value` = 'synergetic' LIMIT 1), 'false', 'true')),
    'false'
);

