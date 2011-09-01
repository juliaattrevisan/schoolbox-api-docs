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
function conType() {
        //detect connection type
        $port = getenv("SERVER_PORT");
        if($port == 80)
                $contype = "http://";
        else if($port == 443)
                $contype = "https://";

        return $contype;
}

//Function for handling error conditions
function error($ec, $message="") {
	global $page;
	if (is_object($page))
		$page->error($ec, $message);
	elseif (is_object($this))
		$this->error($ec, $message);
	else {
		require_once dirname(__FILE__) . '/../../include/SitePage.php';
		$page = new SitePage;
		$page->error($ec, $message);
	}
}

//Function for redirecting to a specified file
function localRedir($file) {
        $contype = conType();
        header("Location: ".$contype.$_SERVER['HTTP_HOST'].$file);
        exit;
}

//This function is used when the session is not correct simply redirects
//user to login page with the requested url
function goLoginPage()
{
		require_once dirname(__FILE__) . '/../../include/SitePage.php';
		$page = new SitePage;
		$page->goLoginPage();
}

////function for returning user ip (tries to work around issues with proxies)
//function getIP()
//{
//	if ($_SERVER['HTTP_CLIENT_IP']) $ip = $_SERVER['HTTP_CLIENT_IP'];
//	else if($_SERVER['HTTP_X_FORWARDED_FOR']) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
//	else if($_SERVER['REMOTE_ADDR']) $ip = $_SERVER['REMOTE_ADDR'];
//	else $ip = "UNKNOWN";
//	return $ip;
//}

function scrubHTML($html) {
	require_once dirname(__FILE__) . '/../../include/HTMLPurifier/HTMLPurifier.auto.php';

	$config = HTMLPurifier_Config::createDefault();
    $config->set('Filter', 'YouTube', true);
	$config->set('Filter', 'Custom', array(new HTMLPurifier_Filter_Quicktime(),
										   new HTMLPurifier_Filter_Shockwave(),
										   new HTMLPurifier_Filter_Flash(),
										   new HTMLPurifier_Filter_WindowsMedia(),
										   new HTMLPurifier_Filter_Real()));
    $config->set('Attr', 'AllowedFrameTargets', array('_blank', '_self', '_parent', '_top'));

	return HTMLPurifier::instance()->purify($html, $config);
}

function scrubTEXT($text) {

	//cleans plain text of all nasty stuff
	//can be used on input to database or output

	//this in built function encode html chars
	//the ENT_QUOTES converts all talking marks
	$text = htmlentities($text, ENT_QUOTES, "UTF-8");

	//get rid of any possible bad chars
	//$text = scrubHTML($text);
	return $text;
}

//function outputDate($format, $date) {
//	//this function attempts to remove output issues associated with mysql dates
//	//removes null dates and times
//
//	if($date == '0000-00-00' || $date == '0000-00-00 00:00:00' || $date < 1)
//		return '';
//
//	//if format contains a time check if it is null if so remove the time from the format
//	if(strpos($format, ':')) {
//		$time = date('g:ia',$date);
//		if($time == '12:00am') {
//			$format = preg_replace('/[a-z]:[a-z]+/i','',$format);
//			return date($format, $date);
//		}
//	}
//
//	return date($format, $date);
//}
//
///**
// * Converts component-based date/time into database-friendly format
// *
// * @param  $date
// * @param  $hour
// * @param  $minute
// * @param  $period
// * @return string
// */
//function getDateTimeByComponents($date, $hour, $minute, $period) {
//	return date(Cfg::get('date_database'), strtotime(scrubTEXT(str_replace('/', '-', $date) . " {$hour}:{$minute}{$period}")));
//}
?>
