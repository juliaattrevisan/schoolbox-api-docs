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
);
