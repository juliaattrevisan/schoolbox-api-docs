# Redis URL and queue configuration
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
    module,
    sequence
) VALUES (
    'taskd_redis_url',
    null,
    'Task Daemon Redis URL',
    'The URL of the Redis instance which this instance''s task daemon listens to',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Resources'),
    'false',
    'true',
    'true',
    'settings',
    null
), (
    'taskd_redis_queue',
    'taskd_queue',
    'Task Daemon Redis Queue',
    'The queue within the Redis instance which this instance''s task daemon listens to',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Resources'),
    'false',
    'true',
    'true',
    'settings',
    null
);

# Set taskd Redis to the same as standard Redis for now
UPDATE config c1
SET c1.value = (SELECT c2.value FROM config c2 WHERE c2.name='redis_url')
WHERE c1.name = 'taskd_redis_url';
