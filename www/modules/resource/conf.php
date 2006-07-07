<?
//This is the configuration file for variable specific to the room booking system

define("IMAGE_LOCATION", "/usr/local/www/intranet/modules/resource/resource_images");

//Permission table
define("PERM_TABLE", serialize(array(
								1 => "Open - Anyone can book",
								2 => "Moderated - Must request booking",
								3 => "Locked - Only owner may place bookings")));

//timesheet variables
define("DAYS_IN_WEEK", 7);
define("FIRST_DAY", 0);
define("START_TIME", 8);
define("END_TIME", 19);

//timetable variables


//dictates what image types are to be allowed to be uploaded
function allowedImages($mime, $extension)
{
	$allowed = array(
		"image/jpeg" => array("jpg", "jpeg", "jpe"),
        "image/pjpeg" => array("pjpg", "jpg", "jpeg", "jpe"),
        "image/png" => array("png"),
        "image/gif" => array("gif"),
		);
	
	if(is_array($allowed[$mime]))
	{
		if(in_array($extension, $allowed[$mime]))
			return true;
	}
	
	return false;
}

?>