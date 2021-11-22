INSERT INTO config(
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module
)
VALUES (
    'transform_cleanup_completed',
    0,
    'Storage Transform cleanup completed',
    'Whether the storage/transform-cleanup.php tool has completed',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE `type` = 'Resources'),
    'false',
    'true',
    'false',
    'settings'
);
