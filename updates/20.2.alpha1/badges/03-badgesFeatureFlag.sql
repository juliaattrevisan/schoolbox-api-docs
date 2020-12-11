INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    sequence
) VALUES ('badges_enabled',
          '1',
          'Enable Badges Module',
          'If toggled to ''ON'', the Badges module (for allowing students to set goals and be awarded achievements that are featured on their profile screen) will be enabled. If toggled to ''OFF'', it will be disabled for all users.',
          (SELECT id FROM lu_config_field WHERE field = 'boolean'),
          '',
          (SELECT id FROM lu_config_type WHERE type = 'Modules'),
          'true',
          'false',
          'false',
          (SELECT COUNT(*)
           FROM config c2
                    JOIN lu_config_type lct on c2.type_id = lct.id
           WHERE type = 'Modules')
         );
