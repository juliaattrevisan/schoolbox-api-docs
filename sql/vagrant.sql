/**
 * Vagrant Localisation fixes.
 *
 * DO NOT STORE SECRETS IN THIS FILE
 *
 * Don't set config values in this file either: they should be set in the call
 * to `schoolbox-data-refresh.sh` in `tools/vagrant-provision.sh` instead, so
 * that that script's module+route refresh call may set the enabled modules
 * appropriately.
 */
UPDATE user SET password='$2y$10$EdUTdtulwEH8tzcfmgGeTuAU0KR5RZjA9JNnLnt0glmvB4pVZlXMm'; /* Encrypted 'password' */
UPDATE user SET password='$2y$10$nCDOhnG78sTv6TrjWLsv8OsPiS1ODvkibaxPB.JlXWFi7fZWth5Du' WHERE username='alaress'; /* Encrypted 'alaress' */
UPDATE config
SET hidden='false', editable='true'
WHERE name IN (
    'site_school_code',
    'debug_mode',
    'debug_xhprof',
    'resource_booking_enabled',
    'pastoral_enabled',
    'ups_groups_enabled',
    'eotc_enabled',
    'fixtures_enabled',
    'qr_login_enabled',
    'summative_report_enabled',
    'kami_worksheets_enabled'
);
