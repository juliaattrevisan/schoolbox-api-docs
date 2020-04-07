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
           'Image Cache Expiry',
           'Images will be cached by the user for this many seconds after first request',
           (SELECT id FROM lu_config_field WHERE `field` = 'number'),
           (SELECT id FROM lu_config_type WHERE `type` = 'Resources'),
           '',
           'true',
           'false',
           'true'
       ), (
           'browser_cache_portrait_timeout',
           '10800', -- 3 hours
           'Portrait Cache Expiry',
           'Portraits will be cached by the user for this many seconds after first request',
           (SELECT id FROM lu_config_field WHERE `field` = 'number'),
           (SELECT id FROM lu_config_type WHERE `type` = 'Resources'),
           '',
           'true',
           'false',
           'true'
       );
