<?php 

/************************************************			
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/


//Site Directives
define("HEADING", "MGS Portal");			//This will appear on the window title of each page
define("SITE_name", "portal");
define("MAX_FILE_SIZE", 10485760); 			//Maximum filesize in bytes allowed to be uploaded (default 10MB) (also set in php.ini)
define("FILE_webroot", "/usr/local/www/mgs/intranet/");		//Where portal is installed and apache configured to point to
define("LOGINGRACE", "60");				//Time measured between logins
define("LOGINALLOWED", "3");				//Logins allowed in grace time


// Note that the following directories should exist outside the webroot and 
// be writable by the apache process

define("FILE_directory", "/usr/local/www/wenona/files/");		//Files uploaded using CMS system will be stored here
define("SUBMISSION_directory", "/usr/local/www/wenona/submissions/");	//All files submitted to submission box stored here
define("RETURNS_directory", "/usr/local/www/wenona/returns/");		//Returned work will be stored in this directory
define("TPL_directory", "/usr/local/www/mgs/templates");		//Where site templates are located


//MySQL config options
define("SQL_host", "localhost");
define("SQL_user", "portal");
define("SQL_passwd", "Qu4LitY");
define("SQL_db", "portal");


//Synergy options
define("SYNERGY_enable", "0");				//Use this to disable synergy modules
define("MSSQL_host","synergy");
define("MSSQL_user","intranet");
define("MSSQL_passwd","Qu4LitY");  
define("MSSQL_db","SynergyOne");  

//Temporary timetable definition untill synergy interface is written
define("TIMETABLE_definition", serialize(array(
		//SS
		array("period"=>6, "day"=>7),
		//Wad
		array("period"=>6, "day"=>7),
		//Grim
		array("period"=>9, "day"=>7)
)));


//Webmail
define("MAIL_enable", "0");				//This will disable webmail module
define("MAIL_retrieval", "imap");			//Options are pop or imap (imap has extra state information)
define("MAIL_host", "mail.mgs.vic.edu.au");
define("MAIL_address", "mgs.vic.edu.au");


//Weather options
define("WEATHER_enable", "0");				//This will disable the weather module
define("WEATHER_CACHE", "7200");  			//cache weather info for 2 hours
define("WEATHER_CURRENT_CACHE_LOCATION", "/usr/local/www/wenona/files/currentweather.xml");	//File to save weather cache in
define("WEATHER_FORECAST_CACHE_LOCATION", "/usr/local/www/wenona/files/forecastweather.xml");	//File to save weather cache in
define("WEATHER_SERVER", "ftp.theweather.com.au");
define("WEATHER_USERNAME", "");
define("WEATHER_PASSWORD", "");
define("WEATHER_LOCATION", "");

define("ALLCAMPUS", 4);					//Value that represents all campuses in the luCampus table
define("HOMEPAGESERVICE", 1);				//Value that represents the homepage module in the service table

define("STUDENTGROUPIDS", serialize(array(3,4,5)));	//List of id numbers representing student groups
define("STAFFGROUPIDS", serialize(array(1,2)));		//Staff group ids



?>
