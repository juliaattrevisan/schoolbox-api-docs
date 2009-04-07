<?php 

/************************************************			
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/


//Site Directives
define("HEADING", "Wenona Portal");			//This will appear on the window title of each page
define("SITE_name", "Wenona");
define("MAX_FILE_SIZE", 10485760); 			//Maximum filesize in bytes allowed to be uploaded (default 10MB) (also set in php.ini)
define("SITE_location", "wenona");				//This defines the location, allows specific code for each location
define("SITE_root", dirname(__FILE__)."/../../");
define('SITE_address', 'http://www.alaress.com.au');


//Security options
define("LOGINGRACE", "60");				//Time measured between logins (secs)
define("LOGINALLOWED", "3");				//Logins allowed in grace time
define("TRACKIP", "0");					//Ensure user session does not change ip (can cause issues)
define("SESSIONLENGTH", "3600");			//Automatically logout users who have been idle for this amount of time (secs)
define("REMEMBERSESSION", "0");				//Allows server to store session indefinetly. Overrides above setting. 
							//Users will only need to login once (has security implications)
define("REGISTER_FIRSTIME", false);                     //Detects no password and checks password against mail server if correct registers account

//LDAP Authentcation
define('LDAP_enable', false);
define('LDAP_host', '');
define('LDAP_user', '');
define('LDAP_pass', '');
define('LDAP_base', '');

// Note that the following directories should exist outside the webroot and 
// be writable by the apache process
//Template Options
define('TEMPLATE_dir', 			SITE_root.'/templates');
define('TEMPLATE_body', 		'bodyTpl');
define('TEMPLATE_content', 		'contentTpl');
define('TPL_directory', 		SITE_root.'/'. SITE_location . "_templates");		//Deprecated option

//CMS options
define("FILE_directory",		SITE_root.'/files');		//Files uploads
define("MYFILE_directory",  		SITE_root.'/myFiles');		//Personal file uploads
define("SUBMISSION_directory", 		SITE_root.'/submissions');	//Student submission files
define("RETURNS_directory", 		SITE_root.'/returns');		//Returned work files
define('DROPBOX_directory', 		SITE_root.'/drop');			//Student dropbox files
define('ZIP_directory', 		SITE_root.'/zip');			//Cache for zip files 
define("THUMBNAIL_IMAGE_CACHE", 	SITE_root.'/thumbnails');	//Thumbnail cache 
define("THUMBNAIL_size", 		100); 
define("FILE_max_upload", 		"10");						//Maximum number of files that can be uploaded via bulk uploader
define("URLS_use_frame", 		false);						//Open links in frame
define('FILE_use_frame', 		false);
define('MIME_magic', 			'/usr/share/file/magic');



//SEARCH & BROWSE Display options
define("FILE_date_format", '%W %M %e %Y @ %l:%i%p');          //Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'
define("NAME_format", "{title} {firstname} {lastname}");

//GROUP options
define("GROUP_staff_only_owner", true);                 //Determines if students can be group owner or not

//FORUM options
define("FORUM_default_view", 'thread'); //Default forum style (flat or thread)
define("FORUM_increment", 10);                                  //Amount of thread or posts per page


//CALENDAR options
define("CALENDAR_default_view", 'week');		//Default view style for calendar (week or month)
define("CALENDAR_time_format", '%l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'
define('DATE_long_format', 'l F jS Y g:ia');
define('DATE_short_format', 'jS M Y g:ia');
define('DATE_database', 'Y-m-d H:i:s');
define('DATE_short_no_time','jS M Y');

//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "portal");
define("SQL_passwd", "tr4ck4ume");
define("SQL_db", "portal_test");


//LDAP
define("LDAP_enable", false);

//Synergy options
define("SYNERGY_enable", true);				//Use this to disable synergy modules
define("MSSQL_host","synergy");
define("MSSQL_user","wenona_portal");
define("MSSQL_passwd","doinavasile");  
define("MSSQL_db","SynergyOne");  

//Synergy campus code lookup table
define('SYN_CAMPUS_Codes', serialize(array(
                1=>'ALL',
		2=>'ALL',
		3=>'ALL',
)));


define('TIMETABLE_day_count',serialize(array(1=>5,5,5)));

//Webmail
define("MAIL_enable", true);				//This will disable webmail module
define("MAIL_interface", "simple");
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "172.16.0.54");
define("MAIL_port", 143);
define("MAIL_smtp_server", "172.16.0.54");
define("MAIL_smtp_port", 25);
define("MAIL_address", "wenona.nsw.edu.au");
define("MAIL_increment", 20);					//Amount of emails per page
define('ROUNDCUBE_db','mysql://'.SQL_user.':'.SQL_passwd.'@'.SQL_host.'/'.SQL_db);

//Weather options
define("WEATHER_enable", true);				//This will disable the weather module
define("WEATHER_CACHE", "7200");  			//cache weather info for 2 hours
define("WEATHER_CURRENT_LOCAL_CACHE_LOCATION", "/var/www/portal/files/currentlocalweather.xml");	//File to save weather cache in
define("WEATHER_CURRENT_AUS_CACHE_LOCATION", "/var/www/portal/files/currentausweather.xml");	//File to save weather cache in
define("WEATHER_FORECAST_CACHE_LOCATION", "/var/www/portal/files/forecastweather.xml");	//File to save weather cache in
define("WEATHER_CURRENT_AUS_FILE", "twc_obs_current_aws_aus.xml");
define("WEATHER_CURRENT_LOCAL_FILE", "twc_obs_current_aws_sydney_9770.xml");
define("WEATHER_FORECAST_FILE", "twc_fcast_local_4day_capcity.xml");
define("WEATHER_SERVER", "ftp.theweather.com.au");
define("WEATHER_USERNAME", "wenona");
define("WEATHER_PASSWORD", "8MAP2m34");
define("WEATHER_LOCATION", "9770");

//RSS Feed 
define('RSS_url', 'http://www.abc.net.au/news/indexes/justin/rss.xml');
define('RSS_cache_dir', '/usr/local/www/files');
define('RSS_cache_time', 3000);
define('RSS_limit', 6);

define("ALLCAMPUS", 0);					//Value that represents all campuses in the luCampus table
define("HOMEPAGESERVICE", 1);				//Value that represents the homepage module in the service table
define("DROPBOX_COMPONENT", 25);
define("FORUM_COMPONENT", 5);

define("STUDENTGROUPIDS", serialize(array(3,5)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids
define("PARENTGROUPIDS", serialize(array(4)));



//homepage config
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



//Feedback
define( '_EMAIL_SUBJECT', 'Portal feedback');
define( '_EMAIL_FROM_DEFAULT', 'itsupport@wenona.nsw.edu.au');
define( '_EMAIL_TO', 'itsupport@wenona.nsw.edu.au');


?>
