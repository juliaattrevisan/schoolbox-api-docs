### create new canvas area
INSERT INTO lu_config_type (type, sequence)
VALUES ('Canvas', 23);

# add magic feature flag
INSERT INTO
    config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
)
VALUES (
           'canvas_enabled',
           'Canvas Integration Enabled',
           false,
           'TBA',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'boolean'
           ),
           (
               SELECT id
               FROM lu_config_type
               WHERE
                   type = 'Canvas'
           ),
           'true',
           'true',
           'false',
           'settings',
           0
       );

### move old canvas configs to new area
update config set type_id = (
    SELECT id
    FROM lu_config_type
    WHERE
        type = 'Canvas'
) where name like 'canvas_%';
