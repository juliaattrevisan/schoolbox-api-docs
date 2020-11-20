# Need to ignore duplicate keys as I've inserted this into value into older datasets for testing.
# (eg installation of sales dataset captured at 20.0.11 into a 20.1.5 host)
INSERT INTO config (
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
    'data_dirs_defaults',
    'purifier_cache_dir:purifier;twig_cache_dir:twig;backup_dir:files;zip_dir:cache/zip;stream_dir:cache/stream;portrait_dir:cache/portrait;logs_dir:cache/logs;storage_dir:storage',
    'Data directory defaults',
    'Defines the set of data directories and their default relative locations. This must be in DB not code because we need to be able to restore from old DB versions into newer code.',
    (SELECT id from lu_config_field WHERE field = 'input'),
    (SELECT id from lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true',
    'settings'
) ON DUPLICATE KEY UPDATE
    value       = 'purifier_cache_dir:purifier;twig_cache_dir:twig;backup_dir:files;zip_dir:cache/zip;stream_dir:cache/stream;portrait_dir:cache/portrait;logs_dir:cache/logs;storage_dir:storage',
    title       = 'Data directory defaults',
    description = 'Defines the set of data directories and their default relative locations. This must be in DB not code because we need to be able to restore from old DB versions into newer code.',
    field_id    = (SELECT id from lu_config_field WHERE field = 'input'),
    type_id     = (SELECT id from lu_config_type WHERE type = '3rd Party Services'),
    editable    = 'false',
    hidden      = 'true',
    required    = 'true',
    module      = 'settings';
