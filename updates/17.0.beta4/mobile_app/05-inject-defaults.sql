/* Inject the appropriate app ID data */
UPDATE `mobile_app_registry` SET app_id = 'apns_default' WHERE provider = 'APNS';
UPDATE `mobile_app_registry` SET app_id = 'gcm_default'  WHERE provider = 'GCM';
