/*
 * Pull any existing data across from the config table
 * To work properly, this needs to not attempt to coerce NULL into '',
 * so temporarily set strict mode. Either of the queries will "fail" if
 * that particular provider is not configured on this server.
 */

SET sql_mode = 'STRICT_ALL_TABLES';

INSERT INTO mobile_app
    SELECT
        'gcm_default',
        'GCM',
        MAX(CASE WHEN `name` = 'push_gcm_api_key' THEN NULLIF(`value`, '') ELSE NULL END),
        NULL
    FROM `config`;

SET sql_mode = '';
