INSERT INTO config (name, title, field_id, type_id)
VALUES
    (
        'ibis_endpoint',
        'IB Endpoint',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'International Baccalaureate')
    ),
    (
        'ibis_app_id',
        'IB App ID',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'International Baccalaureate')
    ),
    (
        'ibis_secret_key',
        'IB Secret Key',
        (SELECT id FROM lu_config_field WHERE field = 'password'),
        (SELECT id FROM lu_config_type WHERE type = 'International Baccalaureate')
    );
