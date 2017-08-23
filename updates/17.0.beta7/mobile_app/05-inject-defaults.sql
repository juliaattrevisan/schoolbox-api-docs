/*
 * Inject the appropriate app ID data
 *
 * These two queries can fail in the circumstance where a push app was registered, devices registered, then the apps
 * UN-registered (leaving the device registrations in place). In that (highly unlikely) circumstance, failure is acceptable
 * and we can manually repair the registration data.
 *
 * For that reason, the DELETE query is in this file so that it *only* executes if the two queries before it pass.
 * Otherwise you lose all your device registrations.
 */

UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE provider = 'APNS' LIMIT 1) WHERE provider = 'APNS';
UPDATE `mobile_app_registry` SET app_id = (SELECT id FROM mobile_app WHERE provider = 'GCM' LIMIT 1) WHERE provider = 'GCM';

-- Remove registrations for non-existent apps
DELETE r FROM mobile_app_registry r
  LEFT JOIN mobile_app a ON r.app_id = a.id
WHERE a.id IS NULL;
