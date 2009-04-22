<?php 

/************************************************			
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/


//Site Directives
define("HEADING", "Ormond Portal");			//This will appear on the window title of each page
define("SITE_name", "Ormond College");

define("SITE_location", "ormond");				//This defines the location, allows specific code for each location
define("SITE_root", dirname(__FILE__)."/../../");
define('SITE_address', 'http://www.ormond.unimelb.edu.au');


//Security options
define("LOGINGRACE", "60");				//Time measured between logins (secs)
define("LOGINALLOWED", "3");				//Logins allowed in grace time
define("TRACKIP", false);					//Ensure user session does not change ip (can cause issues)
define("SESSIONLENGTH", "7200");			//Automatically logout users who have been idle for this amount of time (secs)
define("REMEMBERSESSION", false);				//Allows server to store session indefinetly. Overrides above setting. 
											//Users will only need to login once (has security implications)
define("REGISTER_FIRSTIME", false);			//Detects no password and checks password against mail server if correct registers account

//LDAP Authentcation
define('LDAP_enable', true);
define('LDAP_host', 'ldap://francis2.ormond.unimelb.edu.au');
define('LDAP_user', 'cn=admin,dc=ormond,dc=unimelb,dc=edu,dc=au');
define('LDAP_pass', 'cHin4#');
define('LDAP_base', 'dc=ormond,dc=unimelb,dc=edu,dc=au');

// Note that the following directories should exist outside the webroot and 
// be writable by the apache process

//Template Options
define('TEMPLATE_dir', 			SITE_root.'/templates');
define('TEMPLATE_body', 		'bodyTpl');
define('TEMPLATE_content', 		'contentTpl');
define('TPL_directory', 		SITE_root.'/'. SITE_location . "_templates");		//Deprecated option

//CMS options
define("FILE_directory",		SITE_root.'/files');		//Files uploads
define("MYFILE_directory",  	SITE_root.'/myfiles');		//Personal file uploads
define("SUBMISSION_directory", 	SITE_root.'/submissions');	//Student submission files
define("RETURNS_directory", 	SITE_root.'/returns');		//Returned work files
define('DROPBOX_directory', 	SITE_root.'/drop');			//Student dropbox files
define('ZIP_directory', 		SITE_root.'/zip');			//Cache for zip files 
define("THUMBNAIL_IMAGE_CACHE", SITE_root.'/files/thumbnails');	//Thumbnail cache 
define("THUMBNAIL_size", 		100); 
define("FILE_max_upload", 		"10");						//Maximum number of files that can be uploaded via bulk uploader
define("MAX_FILE_SIZE", 		10485760); 					//Maximum filesize in bytes allowed to be uploaded (default 10MB) (also set in php.ini)
define("URLS_use_frame", 		false);						//Open links in frame
define('FILE_use_frame', 		false);
//define('MIME_magic', 			'/usr/share/file/magic');
define('NEWS_teacher_edit_other', true);

//GROUP options
define("GROUP_staff_only_owner", false);			//Determines if students can be group owner or not

//FORUM options
define("FORUM_default_view", 'thread'); 		//Default forum style (flat or thread)
define("FORUM_increment", 10);					//Amount of thread or posts per page

//CALENDAR options
define("CALENDAR_default_view", 'week');		//Default view style for calendar (week or month)
define("CALENDAR_time_format", '%l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'

//SEARCH & BROWSE Display options
define("FILE_date_format", '%W %M %e %Y @ %l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'


//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "intranet");
define("SQL_passwd", "QuaSiYm0");
define("SQL_db", "intranet");


//Synergy options
define("SYNERGY_enable", false);				//Use this to disable synergy modules
define("MSSQL_host","synergy");
define("MSSQL_user","intranet");
define("MSSQL_passwd","");  
define("MSSQL_db","SynergyOne");  

//Synergy campus code lookup table
/*define('SYN_CAMPUS_Codes', serialize(array(
		1=>'SEN',
		2=>'WAD',
		3=>'GRIM',
)));

//Timetable days as this is not found in synergetic and it can be a bit hard to calculate pre-timetable
define('TIMETABLE_day_count',serialize(array(1=>7,7,5)));
*/

//Webmail
define("MAIL_enable", true);				//This will disable webmail module
define("MAIL_interface", "roundcube");
define("MAIL_db", "roundcubemail");
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "mail.ormond.unimelb.edu.au");
define("MAIL_port", 143);
define("MAIL_smtp_server", "igor.ormond.unimelb.edu.au");
define("MAIL_smtp_port", 25);
define("MAIL_address", "ormond.unimelb.edu.au");
define("MAIL_increment", 20);					//Amount of emails per page


//Weather options
define("WEATHER_enable", false);				//This will disable the weather module
define("WEATHER_CACHE", "7200");  			//cache weather info for 2 hours
define("WEATHER_CURRENT_CACHE_LOCATION", "/usr/local/www/portal/files/currentweather.xml");	//File to save weather cache in
define("WEATHER_FORECAST_CACHE_LOCATION", "/usr/local/www/portal/files/forecastweather.xml");	//File to save weather cache in
define("WEATHER_SERVER", "ftp.theweather.com.au");
define("WEATHER_CURRENT_FILE", "obs_current_aws_vic.xml");
define("WEATHER_FORECAST_FILE", "d_fcast_city_4day.xml");
define("WEATHER_USERNAME", "");
define("WEATHER_PASSWORD", "");
define("WEATHER_LOCATION", "");

//RSS Feed 
define('RSS_url', 'http://www.abc.net.au/news/indexes/justin/rss.xml');
define('RSS_cache_dir', SITE_root.'/files/');
define('RSS_cache_time', 3000);
define('RSS_limit', 6);

define("ALLCAMPUS", 4);					//Value that represents all campuses in the luCampus table
define("HOMEPAGESERVICE", 1);				//Value that represents the homepage module in the service table
define("DROPBOX_COMPONENT", 25);
define("FORUM_COMPONENT", 5);

define("STUDENTGROUPIDS", serialize(array(3)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids
define("PARENTGROUPIDS", serialize(array(6)));		//Parent group ids

// Name format display options.
define("NAME_FORMAT", "{title} {firstname} {lastname}");



//homepage config
define('HOMEPAGE_colors', serialize(array(
	'left_head_font' => '000000', 
	'left_head_bg' => 'f9f8f1', 
	'left_font' => '000000', 
	'left_bg_primary' => 'FFFFFF', 
	'left_bg_secondary' => 'dbdbdb', 
	'left_link' => 'cf0034', 
	'left_hover' => 'c3536f', 
	'right_head_font' => '000000', 
	'right_head_bg' => 'f9f8f1', 
	'right_font' => '000000', 
	'right_bg_primary' => 'FFFFFF', 
	'right_bg_secondary' => 'dbdbdb', 
	'right_link' => 'cf0034', 
	'right_hover' => 'c3536f',  
	
)));

//module config options
define('HELPDESK_enable', true);
define('HELPDESK_location', '/var/www/helpdesk');
define('HELPDESK_db', 'helpdesk');
?>
