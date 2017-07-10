/* yes, ASCII is on purpose here */

CREATE TABLE `mobile_app` (
  `app_id` VARCHAR(128) NOT NULL,
  `provider` ENUM('APNS','GCM') NOT NULL,
  `key` VARCHAR(512) NOT NULL,
  `cert_hash` VARCHAR(40) NULL,
  PRIMARY KEY (`app_id`),
  FOREIGN KEY (`cert_hash`) REFERENCES `storage`(`hash`) ON UPDATE NO ACTION ON DELETE NO ACTION
) CHARACTER SET ASCII COLLATE ascii_general_ci;

/*
 * Pull any existing data across from the config table
 * To work properly, this needs to not attempt to coerce NULL into '',
 * so temporarily set strict mode. Either of the queries will "fail" if
 * that particular provider is not configured on this server.
 */

SET sql_mode = 'STRICT_ALL_TABLES';

INSERT INTO mobile_app
    SELECT
        'apns_default',
        'APNS',
        MAX(CASE WHEN `name` = 'push_apns_cert_key' THEN `value` ELSE '' END),
        MAX(CASE WHEN `name` = 'push_apns_cert_path' THEN NULLIF(`value`, '') ELSE NULL END)
    FROM `config`;

INSERT INTO mobile_app
    SELECT
        'gcm_default',
        'GCM',
        MAX(CASE WHEN `name` = 'push_gcm_api_key' THEN NULLIF(`value`, '') ELSE NULL END),
        NULL
    FROM `config`;

SET sql_mode = '';

/* Add the new column */
ALTER TABLE `mobile_app_registry`
  ADD COLUMN `app_id` VARCHAR(128) NOT NULL CHARSET ascii COLLATE ascii_general_ci AFTER `provider`,
  CHARACTER SET ASCII COLLATE ascii_general_ci;

/* Inject the appropriate app ID data */
UPDATE `mobile_app_registry` SET app_id = 'apns_default' WHERE provider = 'APNS';
UPDATE `mobile_app_registry` SET app_id = 'gcm_default'  WHERE provider = 'GCM';

/** Now that the data is in, enforce the foreign key */
ALTER TABLE `mobile_app_registry`
  ADD FOREIGN KEY (`app_id`) REFERENCES `mobile_app`(`app_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;

/* hide the old config options (don't delete right away, we might need them) */
UPDATE `config`
SET hidden = 'true'
WHERE `name` LIKE '%push_gcm%' OR `name` LIKE '%push_apns%';
