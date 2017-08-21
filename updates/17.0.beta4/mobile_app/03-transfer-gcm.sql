/*
 * Pull any existing data across from the config table
 */

INSERT INTO mobile_app
    SELECT
        NULL,
        'gcm_default',
        'GCM',
        value,
        NULL
    FROM `config` WHERE name = 'push_gcm_api_key' and (value != '' and value is not null  );

