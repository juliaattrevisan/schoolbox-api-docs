/* Vagrant Localisation fixes */
/* DO NOT STORE SECRETS IN THIS FILE */
UPDATE config SET editable='true' WHERE name = 'site_school_code';
UPDATE user SET password=PASSWORD('password');
UPDATE user SET password=PASSWORD('alaress') WHERE username='alaress';
UPDATE user SET password=PASSWORD('286') WHERE username='286';
UPDATE config SET hidden='false' WHERE name IN ('debug_mode','debug_xhprof');
UPDATE config SET editable='true' WHERE name IN ('boarder_leave_enabled','resource_booking_enabled','push_notifications_enabled','pastoral_enabled','ups_groups_enabled','eotc_enabled','fixtures_enabled','qr_login_enabled','summative_report_enabled');
