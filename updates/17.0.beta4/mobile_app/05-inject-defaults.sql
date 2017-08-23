/* Inject the appropriate app ID data */
UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE provider = 'APNS' LIMIT 1) WHERE provider = 'APNS';
UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE provider = 'GCM' LIMIT 1) WHERE provider = 'GCM';