/*
 * Pull any existing data across from the config table
 */

INSERT INTO mobile_app
SELECT
    NULL,
    'apns_default',
    'APNS',
    c1.value,
    c2.value
FROM `config` c1 JOIN `config` c2  WHERE
    (c1.name = 'push_apns_cert_key') AND
    (c2.name = 'push_apns_cert_path' and (c2.value != '' and c2.value is not null  ));
