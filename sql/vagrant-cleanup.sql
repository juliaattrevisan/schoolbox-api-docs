/* Prepare DEV data for usage in other environments */
/* DO NOT STORE SECRETS IN THIS FILE */
UPDATE config SET value='0' WHERE name='ldap_enable';
UPDATE config SET value='' WHERE name='ldap_host';
UPDATE config SET value='' WHERE name='ldap_user';
UPDATE config SET value='' WHERE name='ldap_pass';
UPDATE config SET value='DC=school,DC=local' WHERE name='ldap_base';
UPDATE config SET value='' WHERE name='mail_smtp_host';
UPDATE config SET value='' WHERE name='mail_smtp_port';
UPDATE config SET value='0' WHERE name='mail_enable';
UPDATE config SET value='schoolbox' WHERE name='external_type';
UPDATE config SET value='' WHERE name='external_db_host';
UPDATE config SET value='' WHERE name='external_db_db';
UPDATE config SET value='' WHERE name='external_db_host';
UPDATE config SET value='schoolbox' WHERE name='site_hostname';
UPDATE config SET value='school.edu' WHERE name='site_domain';
UPDATE config SET value='schoolcode' WHERE name='site_location';
UPDATE config SET value='Schoolbox' WHERE name='title';
UPDATE config SET editable='false' WHERE name = 'site_location';
UPDATE config SET hidden='true',value='0' WHERE name IN ('debug_mode','debug_xhprof');
UPDATE config SET value='0' WHERE name = 'debug_show_stack_trace';
UPDATE config SET value='0' WHERE name = 'debug_templates';
UPDATE config SET value='0',editable='false' WHERE name IN ('boarder_leave_enabled','resource_booking_enabled','push_notifications_enabled','pastoral_enabled','ups_groups_enabled','eotc_enabled','fixtures_enabled');
UPDATE config SET value='' WHERE name='external_db_user';
UPDATE config SET value='' WHERE name='external_db_passwd';
UPDATE config SET value='' WHERE name='external_portal';
UPDATE config SET value='' WHERE name='weather_api_key';
UPDATE config SET value='' WHERE name='twitter_api_consumer_key';
UPDATE config SET value='' WHERE name='twitter_api_consumer_secret';
UPDATE config SET value='' WHERE name='twitter_api_oauth_token';
UPDATE config SET value='' WHERE name='twitter_api_oauth_token_secret';
UPDATE config SET value='' WHERE name='site_type';
UPDATE config SET value='' WHERE name='site_school_code';
UPDATE config SET value='0' WHERE name='debug_show_stack_trace';
UPDATE config SET value='' WHERE name='kami_api_key';
UPDATE config SET value='' WHERE name='saml_idp_metadata_url';
UPDATE config SET value='' WHERE name='saml_public_cert';
UPDATE config SET value='' WHERE name='saml_private_cert';



