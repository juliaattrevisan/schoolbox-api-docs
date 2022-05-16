/* Vagrant Localisation fixes */
/* DO NOT STORE SECRETS IN THIS FILE */
UPDATE config SET editable='true' WHERE name = 'site_school_code';
UPDATE user SET password=PASSWORD('password');
UPDATE user SET password=PASSWORD('alaress') WHERE username='alaress';
UPDATE user SET password=PASSWORD('286') WHERE username='286';
UPDATE config SET value='0' WHERE name='ldap_enable';
UPDATE config SET value='127.0.0.1' WHERE name='mail_smtp_host';
UPDATE config SET value='1025' WHERE name='mail_smtp_port';
UPDATE config SET value='1' WHERE name='mail_enable';
UPDATE config SET value='vagrant' WHERE name='site_hostname';
UPDATE config SET value='dev.schoolbox.cloud' WHERE name='site_domain';
UPDATE config SET value='demo' WHERE name='site_school_code';
UPDATE config SET value='Schoolbox [vagrant]' WHERE name='title';
UPDATE config SET hidden='false' WHERE name IN ('debug_mode','debug_xhprof');
UPDATE config SET value='/' WHERE name='amqp_vhost';
UPDATE config SET value='guest' WHERE name='amqp_user';
UPDATE config SET value='guest' WHERE name='amqp_pass';
UPDATE config SET value='localhost' WHERE name='amqp_host';
UPDATE config SET value='tcp://127.0.0.1:6379' WHERE name='redis_url';
UPDATE config SET value='1' WHERE name = 'debug_templates';
UPDATE config SET value='1',editable='true' WHERE name IN ('boarder_leave_enabled','resource_booking_enabled','push_notifications_enabled','pastoral_enabled','ups_groups_enabled','eotc_enabled','fixtures_enabled','qr_login_enabled','summative_report_enabled');
UPDATE config SET value='schoolbox' WHERE name='external_db_user';
UPDATE config SET value='https://portal.synergetic.dev.schoolbox.cloud' WHERE name='external_portal';
UPDATE config SET value='dev' WHERE name='site_type';
UPDATE config SET value='1' WHERE name='debug_show_stack_trace';
UPDATE config SET value='1' WHERE name = 'strict_templates';
UPDATE config SET value='1' WHERE name = 'debug_templates';
UPDATE config SET value='http://localhost:3000' WHERE name = 'gotenberg_host';
