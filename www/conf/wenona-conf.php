<?php 

/************************************************			
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/


//Site Directives
define("HEADING", "Wenona Portal");			//This will appear on the window title of each page
define("SITE_name", "wenonaportal");
define("MAX_FILE_SIZE", 10485760); 			//Maximum filesize in bytes allowed to be uploaded (default 10MB) (also set in php.ini)
define("SITE_location", "wenona");				//This defines the location, allows specific code for each location

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

//CMS options
define("FILE_directory", "/usr/local/www/files");		//Files uploaded using CMS system will be stored here
define("FILE_max_upload", "10");
define("CMS_allow_move", true);
define("SUBMISSION_directory", "/usr/local/www/submissions");	//All files submitted to submission box stored here
define("RETURNS_directory", "/usr/local/www/returns");		//Returned work will be stored in this directory
define("MYFILE_directory", "/usr/local/www/myfiles");
define("TPL_directory", "/usr/local/www/templates");		//Where site templates are located
define("THUMBNAIL_IMAGE_CACHE", "/usr/local/www/files/thumbnails");		//Thumbnail cache for 
define("THUMBNAIL_size", 100);
define("URLS_use_frame", false);						//Open links in frame
define('ZIP_directory', '/usr/local/www/zip');
//define('MIME_magic', '/usr/share/file/magic');
define('DROPBOX_directory', '/usr/local/www/dropbox');

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

//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "portal");
define("SQL_passwd", "tr4ck4ume");
define("SQL_db", "portal");


//LDAP
define("LDAP_enable", false);

//Synergy options
define("SYNERGY_enable", true);				//Use this to disable synergy modules
define("MSSQL_host","Synergy");
define("MSSQL_user","wenona_portal");
define("MSSQL_passwd","doinavasile");  
define("MSSQL_db","SynergyOne");  

//Synergy campus code lookup table
define('SYN_CAMPUS_Codes', serialize(array(
                1=>'SEN',
		2=>'JUN',
)));


define('TIMETABLE_day_count',serialize(array(1=>5,5)));

//Webmail
define("MAIL_enable", true);				//This will disable webmail module
define("MAIL_interface", "simple");
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "172.16.1.32");
define("MAIL_port", 143);
define("MAIL_smtp_server", "172.16.1.32");
define("MAIL_smtp_port", 25);
define("MAIL_address", "wenona.nsw.edu.au");
define("MAIL_increment", 20);					//Amount of emails per page

//Weather options
define("WEATHER_enable", true);				//This will disable the weather module
define("WEATHER_CACHE", "7200");  			//cache weather info for 2 hours
define("WEATHER_CURRENT_LOCAL_CACHE_LOCATION", "/usr/local/www/files/currentlocalweather.xml");	//File to save weather cache in
define("WEATHER_CURRENT_AUS_CACHE_LOCATION", "/usr/local/www/files/currentausweather.xml");	//File to save weather cache in
define("WEATHER_FORECAST_CACHE_LOCATION", "/usr/local/www/files/forecastweather.xml");	//File to save weather cache in
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
define("DROPBOX_COMPONENT", 27);
define("FORUM_COMPONENT", 5);

define("STUDENTGROUPIDS", serialize(array(3)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids
define("PARENTGROUPIDS", serialize(array(4)));



//homepage config
define('HOMEPAGE_colors', serialize(array(
			'large_head_fontcolour' => 'FFFFFF', 
			'large_head_bgcolour' => '002750', 
			'large_text_fontcolour' => '000000', 
			'large_text_bgcolour' => 'FFFFFF', 
			'small_head_fontcolour' => 'FFFFFF', 
			'small_head_bgcolour' => '9EC6D9', 
			'small_text_fontcolour' => '000000', 
			'small_text_bgcolour' => 'CCCCCC'
		)));



//Feedback
define( '_EMAIL_SUBJECT', 'Portal feedback');
define( '_EMAIL_FROM_DEFAULT', 'portal_feedback@wenona.nsw.edu.au');
define( '_EMAIL_TO', 'portal_support@wenona.nsw.edu.au');


?>
