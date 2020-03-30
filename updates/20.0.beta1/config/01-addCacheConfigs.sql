insert into config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    options,
    editable,
    hidden,
    required
)
VALUES (
    'browser_cache_image_timeout',
    '10800', -- 3 hours
    'Browser cache image timeout',
    'Browsers will cache images for this many seconds',
    16, -- number
    4, -- resources
    '',
    'true',
    'false',
    'false'
), (
    'browser_cache_portrait_timeout',
    '10800', -- 3 hours
    'Browser cache portrait timeout',
    'Browsers will cache portraits for this many seconds',
    16, -- number
    4, -- resources
    '',
    'true',
    'false',
    'false'
);
