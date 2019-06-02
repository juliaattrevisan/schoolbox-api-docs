INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'external_ms_sql_server_driver',
    'dblib',
    'Preferred MS SQL Server driver',
    'Preferred PDO database driver to use when connecting to SIS via MS SQL Server databases.',
    (SELECT id FROM lu_config_field WHERE field = 'select'),
    'dblib:sqlsrv',
    (SELECT id FROM lu_config_type WHERE type = 'External DB'),
    'true',
    'false',
    'true',
    'settings'
);
