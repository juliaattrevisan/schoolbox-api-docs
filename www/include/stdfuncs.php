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

function formatName($format, $userInfo = array('title' => '', 'username' => '', 'firstname' => '', 'lastname' => '')){
	// Check for custom format
	if(empty($format)){
		$format = NAME_FORMAT;
	}
	
	// Replace tags
	foreach($userInfo as $tagName => $tagValue){
		// Fill empty var with session data if any
		if(empty($tagValue) and !empty($_SESSION[$tagName])){
			$tagValue = $_SESSION[$tagName];
		}
		$format = str_replace('{' . $tagName . '}', $tagValue, $format);
	}
	
	return $format;
}
?>
