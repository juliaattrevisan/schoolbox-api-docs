<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	A collection of standard common functions				*
*   $Id: stdfuncs.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

//returns the connection type (http:// or https://)
function conType()
{
        //detect connection type
        $port = getenv("SERVER_PORT");
        if($port == 80)
                $contype = "http://"; 
        else if($port == 443)
                $contype = "https://";
        
        return $contype;
}
        
//Function for handling error conditions
function error($ec, $message="")
{
	require_once($_SERVER['DOCUMENT_ROOT'].'/include/include.php');

	$db = new dbManager;

	$query = "SELECT message FROM error WHERE id = '$ec'";
	$error = $db->dbGetSingleRow($query);

	$output = str_replace("%MESSAGE%", $message, $error['message']);

	//append a back button to all errors
	$output .= "<br><br><a href=\"javascript:history.back()\">Back</a>";

	$t_body = new Template(TPL_directory);
	$t_body->set_file("tplERROR", "error.htm");
	$t_body->set_var("TITLE", HEADING." - ERROR!");
	
	$t_body->set_var("OUTPUT", $output);
	$t_body->pparse("finaloutput", "tplERROR");

        exit;

}
 
//Function for redirecting to a specified file
function localRedir($file)
{
        $contype = conType();
        
        header("Location: ".$contype.$_SERVER['HTTP_HOST'].$file);
        exit;
}

//This function is used when the session is not correct simply redirects
//user to login page with the requested url
function goLoginPage()
{
        $page = rawurlencode($_SERVER['REQUEST_URI']);
        localRedir("/login.php?page={$page}");
}


//function for returning user ip (tries to work around issues with proxies)
function getIP() 
{
	if ($_SERVER['HTTP_CLIENT_IP']) $ip = $_SERVER['HTTP_CLIENT_IP'];
	else if($_SERVER['HTTP_X_FORWARDED_FOR']) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else if($_SERVER['REMOTE_ADDR']) $ip = $_SERVER['REMOTE_ADDR'];
	else $ip = "UNKNOWN";
	return $ip;
} 

//converts a size integer to human readable size
function bytesToString($size) {
       $a = array("B", "KB", "MB", "GB", "TB", "PB");

       for($i = 0; $size >= 1024; $i++) {
              $size /= 1024;
       }

      return round($size,2)." ".$a[$i];
}

function scrubHTML($html)
{
	//cleans html of all nasty stuff
	//and converts everything into html safe for php and browser output
	//can be used on input to database or output

	$search = array('\\\\',			// To protect from \\ backreferences
			'$',			// Dollars are important else they can cause backreferences
			'\\\\',			// To protect from \\ backreferences
			'<script',		// Remove javascript markup
			'</script>',			
		);
	
	$replace = array('&#92;',
			'&#36;',
			'&#92;',
			'',
			'',
		);

	$html = str_replace($search, $replace, $html);

	return $html;
}

function scrubTEXT($text)
{
	//cleans plain text of all nasty stuff
	//can be used on input to database or output

	//this in built function encode html chars
	//the ENT_QUOTES converts all talking marks
	$text = htmlentities($text, ENT_QUOTES);

	//get rid of any possible bad chars
	$text = scrubHTML($text);
	return $text;
}

function isLocalURL($url)
{	
	//returns true if the url is for a local page
	if( strncasecmp($url, "http://".SITE_name, 7+strlen(SITE_name)) && 
	strncasecmp($url, "https://".SITE_name, 8+strlen(SITE_name)) )
		return false;
	else
		return true;
}

function formatName($userInfo, $format = NULL){
	// Check for custom format
	if(empty($format)){
		$format = NAME_format;
	}
	
	// Replace tags
	foreach($userInfo as $tagName => $tagValue){
		$format = str_replace('{' . $tagName . '}', $tagValue, $format);
	}
	
	return $format;
}

function formatEmail($email) {
	if($email['local_email']) {
		if(SITE_location == 'wenona' ) {
			if(isStaff($_SESSION['gid'])) {		//if wenona only return address if they are staff then only return a uid
				if($email['uid'] && stristr($_SESSION['mailhandle'],'createMail.php')) 
					return $email['uid'];
				else 
					return $email['username']."@".MAIL_address;
			}
			else 
				return false;
		}
		else 
			return $email['username']."@".MAIL_address;
	}
	else if($email['alt_email'])
		return $email['alt_email'];
	else 
		return false;
}

function cutSentence($content, $chars) {
	/* Cut off a summary at the fullstop within the character limit, alternatively just at the word */
	$summary=substr($content, 0, $chars);
	$position=strrpos($summary, ".")+1;
	$positionQ = strrpos($summary, "?")+1;
	$positionB = strrpos($summary, "!")+1;
	if($position > 1 && $position > $positionQ && $position > $positionB) {
		$output=substr($summary, 0, $position);
		return $output;
	}
	else if($positionQ > 1 && $positionQ > $positionB) {
		$output=substr($summary, 0, $positionQ);
		return $output;
	}
	else if($positionB > 1) {
		$output=substr($summary, 0, $positionB);
		return $output;
	}
	else {
		$position = strrpos($summary, " ");
		$output=substr($summary, 0, $position);
		if($output)
			return $output."...";
		else
			return "";
	}
}

function outputDate($format, $date) {
	//this function attempts to remove output issues associated with mysql dates
	//removes null dates and times
	
	if($date == '0000-00-00' || $date == '0000-00-00 00:00:00' || $date < 1)
		return '';
	
	//if format contains a time check if it is null if so remove the time from the format
	if(strpos($format, ':')) {
		$time = date('g:ia',$date);
		if($time == '12:00am') {
			$format = preg_replace('/[a-z]:[a-z]+/i','',$format);
			return date($format, $date);
		}
	}
	
	return date($format, $date);
}


function isEmail($email) {
	return eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$",$email);
}

function isUrl($url) {
	$cmp = @parse_url($url);
	if (is_array($cmp) && isset($cmp['host'])){
		return true;
	}
	return false;
}

function delfile($str)
{
    foreach(glob($str) as $fn) {
        unlink($fn);
    }
} 
?>
