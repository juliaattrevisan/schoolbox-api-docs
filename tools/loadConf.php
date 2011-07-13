<?php

require_once(dirname(__FILE__)  .'/../www/conf/conf.php');
require_once(dirname(__FILE__) . '/../include/Config.php');

$configHandler = new Config;

/* Load conf.php into config table in db */

/* Site */

$config[] =  array('name' => 'title', 'value' => HEADING, 'title' => 'Title', 'description'=>'Title of site displayed at top of browser window', 'field'=> 'input', 'type'=>'Site','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'school_name', 'value' => SITE_name, 'title' => 'School Name', 'description'=>'Name of school displayed in footer', 'field'=> 'input', 'type'=>'Site','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'school_adddress', 'value' => SITE_address, 'title' => 'School Website Address', 'description'=>'Address of school website', 'field'=> 'input', 'type'=>'Site','editable'=>'true','hidden'=>'false');


$config[] =  array('name' => 'site_location', 'value' => SITE_location, 'title' => 'Site Identifier', 'description'=>'Name of site used to identify school in custom code and templates', 'field'=> 'input', 'type'=>'Site','editable'=>'false','hidden'=>'false');

$config[] =  array('name' => 'site_hostname', 'value' => SITE_hostname, 'title' => 'Hostname', 'description'=>'Hostname used to access server without the domain', 'field'=> 'input', 'type'=>'Site','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'site_domain', 'value' => SITE_domain, 'title' => 'Domain', 'description'=>'Network domain that is used to access server remotely', 'field'=> 'input', 'type'=>'Site','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'site_detect', 'value' => SITE_detect, 'title' => 'Template Detection', 'description'=>'Method which template is detected', 'field'=> 'select', 'options'=>'none:campus:domain', 'type'=>'Site','editable'=>'true','hidden'=>'false');


/* Security */
$config[] =  array('name' => 'login_grace', 'value' => LOGINGRACE, 'title' => 'Login Grace Time', 'description'=>'Amount of time (secs) between failed logins before brute force lockout', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'login_allowed', 'value' => LOGINALLOWED, 'title' => 'Failed Logins Allowed', 'description'=>'Amount of logins allowed in login grace time before brute force lockout', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'track_ip', 'value' => TRACKIP?'1':'0', 'title' => 'Track Session IPs', 'description'=>'Track session IPs, prevents session stealing but can cause problems with proxies', 'field'=> 'boolean', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'session_length', 'value' => SESSIONLENGTH, 'title' => 'Session Expiry Time', 'description'=>'Length of idle time (secs) before a user is logged out', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'kerb_enable', 'value' => KERB_enable?'1':'0', 'title' => 'Enable Kerberos SSO', 'description'=>'Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication', 'field'=> 'boolean', 'type'=>'Security','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'ldap_enable', 'value' => LDAP_enable?'1':'0', 'title' => 'Enable LDAP Authentication', 'description'=>'Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication', 'field'=> 'boolean', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_host', 'value' => LDAP_host, 'title' => 'LDAP Host', 'description'=>'Address of LDAP server to which search should be performed', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_user', 'value' => LDAP_user, 'title' => 'LDAP Username', 'description'=>'User account for binding to LDAP, should be complete DN', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_pass', 'value' => LDAP_pass, 'title' => 'LDAP Password', 'description'=>'Password for binding to LDAP server', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_base', 'value' => LDAP_base, 'title' => 'LDAP Search Base', 'description'=>'Base search path for username lookup', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_host_alt', 'value' => defined('LDAP_host2')?LDAP_host2:'', 'title' => 'LDAP Alternate Host', 'description'=>'Address of LDAP server to which search should be performed', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_user_alt', 'value' => defined('LDAP_user2')?LDAP_user2:'', 'title' => 'LDAP Alternate Username', 'description'=>'User account for binding to LDAP, should be complete DN', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_base_alt', 'value' => defined('LDAP_base2')?LDAP_base2:'', 'title' => 'LDAP Alternate Search Base', 'description'=>'Base search path for username lookup', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'ldap_username', 'value' => defined('LDAP_search')?LDAP_search:'sAMAccountName', 'title' => 'LDAP Username Search Field', 'description'=>'Field to search for username in LDAP', 'field'=> 'input', 'type'=>'Security','editable'=>'true','hidden'=>'false');


/* Paths */
$config[] =  array('name' => 'template_dir', 'value' => TEMPLATE_dir, 'title' => 'Template Location', 'description'=>'Location of templates', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'file_dir', 'value' => FILE_directory, 'title' => 'File Location', 'description'=>'Location of files uploaded to resources are stored', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'myfile_dir', 'value' => MYFILE_directory, 'title' => 'MyFiles Location', 'description'=>'Location of personal files uploaded to my files or eportfolio are stored', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'submission_dir', 'value' => SUBMISSION_directory, 'title' => 'Submission Location', 'description'=>'Location of student submissions', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'returns_dir', 'value' => RETURNS_directory, 'title' => 'Returns Location', 'description'=>'Location of teacher returned work', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'dropbox_dir', 'value' => DROPBOX_directory, 'title' => 'Dropbox Location', 'description'=>'Location of files uploaded to dropbox', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'zip_dir', 'value' => ZIP_directory, 'title' => 'ZIP Temp Location', 'description'=>'Location of files uploaded to dropbox', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'thumbnail_dir', 'value' => THUMBNAIL_IMAGE_CACHE, 'title' => 'Image Cache Location', 'description'=>'Location of where thumbnails and resized images are stored after they are generated', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'xml_dir', 'value' => CACHE_xml, 'title' => 'XML Cache Location', 'description'=>'Location of RSS and XML caching', 'field'=> 'input', 'type'=>'Paths','editable'=>'false','hidden'=>'false');


/* Module Settings */
$config[] =  array('name' => 'thumbnail_size', 'value' => THUMBNAIL_size, 'title' => 'Thumbnail pixel size', 'description'=>'Maximum height/width of thumbnails generated for image gallery', 'field'=> 'input', 'type'=>'Resources','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'file_upload_count', 'value' => FILE_max_upload, 'title' => 'File Upload Limit', 'description'=>'The total amount of files that can be uploaded via a single multifile upload page', 'field'=> 'input', 'type'=>'Resources','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'max_file_size', 'value' => MAX_FILE_SIZE, 'title' => 'File Size Limit', 'description'=>'The maximum size (bytes) of a single file that can be uploaded', 'field'=> 'input', 'type'=>'Resources','editable'=>'true','hidden'=>'false');


$config[] =  array('name' => 'news_edit_other', 'value' => NEWS_teacher_edit_other, 'title' => 'Allow Others to Edit News', 'description'=>'Controls whether users with write access to a page can edit each others news posts', 'field'=> 'boolean', 'type'=>'Resources','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'group_staff_only', 'value' => GROUP_staff_only_owner, 'title' => 'Allow Students to be Group Admins', 'description'=>'Allow users in student groups to become group admins, beware of the consequences', 'field'=> 'boolean', 'type'=>'Resources','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'webdav_enable', 'value' => WEBDAV_on?'1':'0', 'title' => 'Enable WebDAV connector in MyFiles', 'description'=>'Turns on webDAV in the MyFiles area', 'field'=> 'boolean', 'type'=>'Resources','editable'=>'false','hidden'=>'false');

$config[] =  array('name' => 'search_all_limit', 'value' => SEARCH_all_limit, 'title' => 'Search Summary Item Limit', 'description'=>'The amount of items shown for each section on the all tab in the search', 'field'=> 'boolean', 'type'=>'Resources','editable'=>'false','hidden'=>'false');
$config[] =  array('name' => 'search_type_limit', 'value' => SEARCH_type_limit, 'title' => 'Search Items Per Page', 'description'=>'The amount of results shown per page in the search results', 'field'=> 'boolean', 'type'=>'Resources','editable'=>'false','hidden'=>'false');




/* Welcome Page */
$config[] =  array('name' => 'welcome_image_folder', 'value' => WELCOME_image_folder, 'title' => 'Image Rotation Folder ID', 'description'=>'The welcome page image scroller utilises this to find images', 'field'=> 'input', 'type'=>'Welcome','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'welcome_rss_url', 'value' => RSS_url, 'title' => 'URL for RSS', 'description'=>'The URL for the RSS feed displayed on the welcome page', 'field'=> 'input', 'type'=>'Welcome','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'welcome_rss_limit', 'value' => RSS_limit, 'title' => 'RSS Item Limit', 'description'=>'The amount of items displayed from the RSS feed on the welcome page', 'field'=> 'input', 'type'=>'Welcome','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'weather_enable', 'value' => WEATHER_enable?'1':'0', 'title' => 'Enable Weather', 'description'=>'Reguarily fetches weather data from Google weather feeds', 'field'=> 'boolean', 'type'=>'Welcome','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'weather_location', 'value' => WEATHER_location, 'title' => 'Weather Location', 'description'=>'By default display weather at this location', 'field'=> 'input', 'type'=>'Welcome','editable'=>'true','hidden'=>'false');



/* Localisation */
$config[] =  array('name' => 'name_format', 'value' => NAME_format, 'title' => 'Name Output Format', 'description'=>'Format of names available fields; {title}, {firstname}, {lastname}', 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'calendar_time_format', 'value' => CALENDAR_time_format, 'title' => 'Calendar Time Format', 'description'=>"Time output style 24 hour = '%k:%i', 12 hour = '%l:%i%p'", 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'date_long', 'value' => DATE_long_format, 'title' => 'Long Date Format', 'description'=>'A PHP date() time format string', 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'date_short', 'value' => DATE_short_format, 'title' => 'Short Date Format', 'description'=>'A PHP date() time format string', 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'date_database', 'value' => DATE_database, 'title' => 'Database Date Format', 'description'=>'A PHP date() time format string', 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'date_notime', 'value' => DATE_short_no_time, 'title' => 'Date with no Time Format', 'description'=>'A PHP date() time format string', 'field'=> 'input', 'type'=>'Localisation','editable'=>'true','hidden'=>'false');

/* External Database */
$config[] =  array('name' => 'external_enable', 'value' => SYNERGY_enable?'1':'0', 'title' => 'Enable External Database', 'description'=>'Schoolbox can integrate with your existing school database to fetch timetable and student records', 'field'=> 'boolean', 'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_type', 'value' => defined('EXTERNAL_db')?EXTERNAL_db:'synergetic', 'title' => 'Database Provider', 'description'=>'The name of the database product currently being used', 'field'=> 'select', 'options'=>'synergetic:tass', 'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_db_host', 'value' => MSSQL_host, 'title' => 'Database Host', 'description'=>'The server hostname where SQL database is located', 'field'=> 'input', 'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_db_user', 'value' => MSSQL_user, 'title' => 'Database User', 'description'=>'SQL user with read access to the required tables, see documentation', 'field'=> 'input',  'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_db_passwd', 'value' => MSSQL_passwd, 'title' => 'Database Password', 'description'=>'SQL user password', 'field'=> 'input', 'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_db_db', 'value' => MSSQL_db, 'title' => 'Database Name', 'description'=>'The name of the database on the SQL server', 'field'=> 'input',  'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_portal', 'value' => SYNERGY_portal, 'title' => 'External DB Portal URL', 'description'=>'Schoolbox can integrate with your existing school database to fetch timetable and student records', 'field'=> 'input', 'type'=>'External DB','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'external_sync_data', 'value' => defined('SYNERGY_sync_email')&&SYNERGY_sync_email?'1':'0', 'title' => 'Syncronise Data', 'description'=>'Controls if we should syncronise the emails with school database, currently only works for parents', 'field'=> 'boolean',  'type'=>'External DB','editable'=>'true','hidden'=>'false');



/* Email */
$config[] =  array('name' => 'mail_enable', 'value' => MAIL_enable?'1':'0', 'title' => 'Enable External Database', 'description'=>'Schoolbox can integrate with your existing school database to fetch timetable and student records', 'field'=> 'boolean', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_type', 'value' => MAIL_interface, 'title' => 'Mail System', 'description'=>'Simple send mail capability, or full imap support with roundcube webmail', 'field'=> 'select', 'options'=>'simple:roundcube', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_host', 'value' => MAIL_host, 'title' => 'Mail Server', 'description'=>'Location of IMAP mail server for use with roundcube', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_host_port', 'value' => MAIL_port, 'title' => 'Mail Host Port', 'description'=>'Port of mail server, typically 143', 'field'=> 'input',  'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_smtp_host', 'value' => MAIL_smtp_server, 'title' => 'SMTP Server', 'description'=>'Address of server to send email via, this should be your email gateway', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_smtp_port', 'value' => MAIL_smtp_port, 'title' => 'SMTP Port', 'description'=>'Port of the SMTP server, typically 25', 'field'=> 'input',  'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_address', 'value' => MAIL_address, 'title' => 'Email Domain', 'description'=>'Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');

$config[] =  array('name' => 'mail_webmail', 'value' => '', 'title' => 'Webmail URL', 'description'=>'URL to access OWA or other existing webmail service.  Should be accessible internally and externally', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');


$config[] =  array('name' => 'mail_from_address', 'value' => FROM_address, 'title' => 'Email From Address', 'description'=>'Emails that originate from Schoolbox will come from this address', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_from_name', 'value' => FROM_name, 'title' => 'Email From Name', 'description'=>'Emails that originate from Schoolbox will come from this name', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');
$config[] =  array('name' => 'mail_support_address', 'value' => CONTACT_address, 'title' => 'Portal Feedback Address', 'description'=>'Requests sent to the Schoolbox feedback form will be sent to this address', 'field'=> 'input', 'type'=>'Email','editable'=>'true','hidden'=>'false');

foreach($config as $c) {
    $configHandler->create($c);
}



?>
