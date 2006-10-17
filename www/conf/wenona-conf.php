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

// Note that the following directories should exist outside the webroot and 
// be writable by the apache process

//CMS options
define("FILE_directory", "/usr/local/www/files");		//Files uploaded using CMS system will be stored here
define("SUBMISSION_directory", "/usr/local/www/submissions");	//All files submitted to submission box stored here
define("RETURNS_directory", "/usr/local/www/returns");		//Returned work will be stored in this directory
define("TPL_directory", "/usr/local/www/templates");		//Where site templates are located
define("THUMBNAIL_IMAGE_CACHE", "/usr/local/www/files/thumbnails");		//Thumbnail cache for 
define("URLS_use_frame", false);						//Open links in frame

//GROUP options
define("GROUP_staff_only_owner", true);                 //Determines if students can be group owner or not

//FORUM options
define("FORUM_default_view", 'thread'); //Default forum style (flat or thread)

//CALENDAR options
define("CALENDAR_default_view", 'week');		//Default view style for calendar (week or month)
define("CALENDAR_time_format", '%l:%i%p');		//Time output style 24 hour = '%k:%i', 12 hour = '%l:%i %p'

//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "portal");
define("SQL_passwd", "tr4ck4ume");
define("SQL_db", "portal");


//Synergy options
define("SYNERGY_enable", true);				//Use this to disable synergy modules
define("MSSQL_host","Synergy");
define("MSSQL_user","wenona_portal");
define("MSSQL_passwd","doinavasile");  
define("MSSQL_db","SynergyOne");  

//Synergy campus code lookup table
define('SYN_CAMPUS_Codes', serialize(array(
                1=>'SS',
)));


//Temporary timetable definition until synergy interface is written
define("TIMETABLE_definition", serialize(array(
		//Senior
		array("period"=>9, 
			"day"=>5, 
			"periodnames"=>array(1=>"Period 0", "Period 1", "Period 2", "Roll Call", "Period 3", "Period 4", "Lunch", "Period 5", "Period 6")),
		//Junior
		array("period"=>9, 
			"day"=>5, 
			"periodnames"=>array(1=>"Period 0", "Period 1", "Period 2", "Period 3", "Period 4", "Lunch", "Period 5", "Period 6", "Period 7")),
)));


//Webmail
define("MAIL_enable", true);				//This will disable webmail module
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "172.16.0.7");
define("MAIL_address", "wenona.nsw.edu.au");


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

define("ALLCAMPUS", 0);					//Value that represents all campuses in the luCampus table
define("HOMEPAGESERVICE", 1);				//Value that represents the homepage module in the service table

define("STUDENTGROUPIDS", serialize(array(3)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids

define( '_EMAIL_SUBJECT', 'Portal feedback');
define( '_EMAIL_FROM_DEFAULT', 'portal_feedback@wenona.nsw.edu.au');
define( '_EMAIL_TO', 'portal_support@wenona.nsw.edu.au');

?>
