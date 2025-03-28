UPDATE config
SET
    editable = 'false',
    value = '518400',
    hidden = 'true'
WHERE name IN (
               'browser_cache_portrait_timeout',
               'browser_cache_image_timeout'
    );
