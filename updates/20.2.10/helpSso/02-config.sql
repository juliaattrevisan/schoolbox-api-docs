INSERT INTO config(
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
    module,
    sequence
)
VALUES (
    'show_schoolbox_help_link',
    0,
    'Show online help link to staff?',
    'If set to ON: staff will see a link to online help in their profile menu; on following it, staff will be automatically signed in if they have a Schoolbox Help account. (You can also add this manually to the top or side menus, by adding a menu item with URL "/help/sso/send".)',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE `module` = 'settings'
        AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Policy')
    )
);
