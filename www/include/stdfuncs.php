<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	A collection of standard common functions				*
*   $Id: stdfuncs.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $
*                                                           *
************************************************************/

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
		require_once dirname(__FILE__) . '/../../include/SitePage.php';
        $contype = SitePage::conType();
        header("Location: ".$contype.$_SERVER['HTTP_HOST'].$file);
        exit;
}

//function scrubHTML($html) {
//	require_once dirname(__FILE__) . '/../../include/HTMLPurifier/HTMLPurifier.auto.php';
//
//	$config = HTMLPurifier_Config::createDefault();
//    $config->set('Filter', 'YouTube', true);
//	$config->set('Filter', 'Custom', array(new HTMLPurifier_Filter_Quicktime(),
//										   new HTMLPurifier_Filter_Shockwave(),
//										   new HTMLPurifier_Filter_Flash(),
//										   new HTMLPurifier_Filter_WindowsMedia(),
//										   new HTMLPurifier_Filter_Real()));
//    $config->set('Attr', 'AllowedFrameTargets', array('_blank', '_self', '_parent', '_top'));
//
//	return HTMLPurifier::instance()->purify($html, $config);
//}

//function scrubTEXT($text) {
//
//	//cleans plain text of all nasty stuff
//	//can be used on input to database or output
//
//	//this in built function encode html chars
//	//the ENT_QUOTES converts all talking marks
//	$text = htmlentities($text, ENT_QUOTES, "UTF-8");
//
//	//get rid of any possible bad chars
//	//$text = scrubHTML($text);
//	return $text;
//}
