INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'external_tass_attendance_show_prod_menu',
    '1',
    'Show Teacher Kiosk menu in Attendance Marking',
    'If enabled, the Teacher Kiosk menu will show in attendance marking screens linked to from Schoolbox',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = 'External DB'),
    'true',
    'false',
    'false'
);
