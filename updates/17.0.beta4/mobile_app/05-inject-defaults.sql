/* Inject the appropriate app ID data */
UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE app_identifier = 'apns_default' ) WHERE provider = 'APNS';
UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE app_identifier = 'gcm_default' ) WHERE provider = 'GCM';
