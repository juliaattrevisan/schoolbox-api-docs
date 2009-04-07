<?php 

/************************************************			
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/


//Site Directives
define("HEADING", 'Shore Portal');			//This will appear on the window title of each page
define("SITE_name", "Shore");
define("MAX_FILE_SIZE", 10485760); 			//Maximum filesize in bytes allowed to be uploaded (default 10MB) (also set in php.ini)
define("SITE_location", "shore");				//This defines the location, allows specific code for each location
define("SITE_root", dirname(__FILE__)."/../../");
define('SITE_address', 'http://www.shore.nsw.edu.au');

//Security options
define("LOGINGRACE", "60");				//Time measured between logins (secs)
define("LOGINALLOWED", "3");				//Logins allowed in grace time
define("TRACKIP", false);					//Ensure user session does not change ip (can cause issues)
define("SESSIONLENGTH", "1800");			//Automatically logout users who have been idle for this amount of time (secs)
define("REMEMBERSESSION", false);				//Allows server to store session indefinetly. Overrides above setting. 
											//Users will only need to login once (has security implications)
define("REGISTER_FIRSTIME", false);			//Detects no password and checks password against mail server if correct registers account

//LDAP Authentcation
define('LDAP_enable', false);
define('LDAP_host', '');
define('LDAP_user', '');
define('LDAP_pass', '');
define('LDAP_base', '');

// Note that the following directories should exist outside the webroot and 
// be writable by the apache process

//Template Options
define('TEMPLATE_dir', '/var/www/portal/templates');
define('TEMPLATE_body', 'bodyTpl');
define('TEMPLATE_content', 'contentTpl');
define("TPL_directory", '/var/www/portal/' . SITE_location . '_templates');		//Deprecated option

//CMS options
define("FILE_directory", "/var/www/portal/files/files");	//Files uploaded using CMS system will be stored here
define("FILE_max_upload", "10");							// The maximum number of uploads for the multi upload system
define("CMS_allow_move", true);
define("MYFILE_directory", "/var/www/portal/files/myFiles");	//Files uploaded using CMS system will be stored here
define("SUBMISSION_directory", "/var/www/portal/files/submissions");	//All files submitted to submission box stored here
define("RETURNS_directory", "/var/www/portal/files/returns");		//Returned work will be stored in this directory
define("THUMBNAIL_IMAGE_CACHE", "/var/www/portal/files/thumbnails");		//Thumbnail cache for 
define("THUMBNAIL_size", 100); 
define("URLS_use_frame", false);						//Open links in frame
define('FILE_use_frame', false);
define('ZIP_directory', '/var/www/portal/files/zip');
define('MIME_magic', '/usr/share/file/magic');
define('DROPBOX_directory', '/var/www/portal/files/drop');

//GROUP options
define("GROUP_staff_only_owner", true);			//Determines if students can be group owner or not

//FORUM options
define("FORUM_default_view", 'thread'); 		//Default forum style (flat or thread)
define("FORUM_increment", 10);					//Amount of thread or posts per page

//CALENDAR options
define("CALENDAR_default_view", 'week');		//Default view style for calendar (week or month)
define("CALENDAR_time_format", '%l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'

define('DATE_long_format', 'l F jS Y g:ia');
define('DATE_short_format', 'jS M Y g:ia');
define('DATE_database', 'Y-m-d H:i:s');
define('DATE_short_no_time','jS M Y');


//SEARCH & BROWSE Display options
define('FILE_date_format', '%W %M %e %Y @ %l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'
define('NAME_format', '{title} {firstname} {lastname}'); 	// Format for output of name

//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "portal");
define("SQL_passwd", "sh0r3db");
define("SQL_db", "portal");


//Synergy options
define("SYNERGY_enable", false);				//Use this to disable synergy modules
define("MSSQL_host","synergy");
define("MSSQL_user","intranet");
define("MSSQL_passwd","Qu4LitY");  
define("MSSQL_db","SynergyOne");  

//Synergy campus code lookup table
define('SYN_CAMPUS_Codes', serialize(array(
		1=>'SEN',
		2=>'WAD',
		3=>'GRIM',
)));

//Timetable days as this is not found in synergetic and it can be a bit hard to calculate pre-timetable
define('TIMETABLE_day_count',serialize(array(1=>7,7,5)));


//Webmail
define("MAIL_enable", false);				//This will disable webmail module
define("MAIL_interface", "roundcube");		// Options are roundcube or simple
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "alderaan.mgs.vic.edu.au");
define("MAIL_port", 143);
define("MAIL_smtp_server", "mail.mgs.vic.edu.au");
define("MAIL_smtp_port", 25);
define("MAIL_address", "mgs.vic.edu.au");
define("MAIL_increment", 20);					//Amount of emails per page
define("MAIL_db", "roundcubemail");
define('ROUNDCUBE_db','mysql://'.SQL_user.':'.SQL_passwd.'@'.SQL_host.'/'.SQL_db);

// mailer
define('MAIL_templates', TEMPLATE_dir);
define('FROM_address', 'portal@lektec.com.au');
define('FROM_name', HEADING);

//RSS Feed 
define('RSS_url', 'http://www.abc.net.au/news/indexes/justin/rss.xml');
define('RSS_cache_dir', '/var/www/portal/files/files/');
define('RSS_cache_time', 3000);
define('RSS_limit', 6);

//Weather options

define("WEATHER_enable", false);				//This will disable the weather module
define("WEATHER_CACHE", "7200");  			//cache weather info for 2 hours


define("WEATHER_CURRENT_CACHE_LOCATION", "/var/www/portal/files/weether/currentweather.xml");	//File to save weather cache in
define("WEATHER_FORECAST_CACHE_LOCATION", "/var/www/portal/files/weather/forecastweather.xml");	//File to save weather cache in
/*
define("WEATHER_SERVER", "ftp.theweather.com.au");
define("WEATHER_CURRENT_FILE", "obs_current_aws_vic.xml");
define("WEATHER_FORECAST_FILE", "d_fcast_city_4day.xml");
define("WEATHER_USERNAME", "");
define("WEATHER_PASSWORD", "");
define("WEATHER_LOCATION", "");
*/

define("ALLCAMPUS", 4);					//Value that represents all campuses in the luCampus table
define("HOMEPAGESERVICE", 1);				//Value that represents the homepage module in the service table
define("FORUM_COMPONENT", 5);

define("STUDENTGROUPIDS", serialize(array(3,5)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids
define("PARENTGROUPIDS", serialize(array(4)));		//Parent group ids

define('HOMEPAGE_colors', serialize(array(
	'left_head_font' => 'FFFFFF', 
	'left_head_bg' => '002750', 
	'left_font' => '000000', 
	'left_bg_primary' => 'FFFFFF', 
	'left_bg_secondary' => 'ECECEC', 
	'left_link' => '002B58', 
	'left_hover' => '0065CE', 
	'right_head_font' => 'FFFFFF', 
	'right_head_bg' => '9EC6D9', 
	'right_font' => '000000', 
	'right_bg_primary' => 'CCCCCC', 
	'right_bg_secondary' => 'ECECEC', 
	'right_link' => '002B58', 
	'right_hover' => '0065CE',  
	
)));

//module config options
define('HELPDESK_enable', false);
define('HELPDESK_location', '/usr/local/www/helpdesk');
define('HELPDESK_db', 'helpdesk');
?>
