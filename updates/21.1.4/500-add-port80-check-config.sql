INSERT INTO config (name, value, title, description, field_id, type_id, editable, hidden, required, module)
VALUES (
    'show_port_80_availability_check',
    (
        SELECT * FROM (
            -- ON if onprem AND LE available
            SELECT IF(COUNT(*) = 2, 1, 0)
            FROM config
            WHERE
                (name = 'site_environment' AND value = 'client-hosted') OR
                (name = 'site_school_code' AND value NOT IN ('ISASCIS','ISATIS','CISGZ'))
        ) t1
    ),
    'Show port 80 availability check',
    'If enabled, show widget on admin dashboard to indicate if port 80 is being routed to server',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = 'Site'),
    'true',
    'true',
    'true',
    'settings'
)
