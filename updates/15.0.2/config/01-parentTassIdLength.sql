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
    'external_tass_parent_id_length',
    -- 7 for all (so far) except Sheldon
    (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` = 'sheldon'), '5', '7')),
    'TASS Parent ID length',
    'Length of parent IDs as stored in TASS. (This needs to be set correctly for split family IDs at the end of a parent ID to be recognised.)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'external_type' AND `c`.`value` = 'tass' LIMIT 1), 'false', 'true')),
    'false'
);
