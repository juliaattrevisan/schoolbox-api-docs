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

//Function
function greeting() {
	if(date("A")=="AM")
		$greeting =  "Good Morning";
	else if(date("G") < 17)
		$greeting =  "Good Afternoon";
	else
		$greeting =  "Good Evening";

	return $greeting;
}

//This function is used when the session is not correct simply redirects
//user to login page with the requested url
function goLoginPage()
{
		require_once dirname(__FILE__) . '/../../include/SitePage.php';
		$page = new SitePage;
		$page->goLoginPage();
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


/*
 * convert an australian date to a unixtimestamp
 *
 * @param	str		$date		date should be in the format of dd-mm-yyyy or dd/mm/yyyy
 * @param	bool	$incTime	if set, try to get the time from the end of the string
 * @return	int					unix timestamp or false if invalid date given
 */
function ausDateToUnix($date, $incTime=false) {

	if (strstr($date, '-')) {
		$seperator = '-';
	} else if (strstr($date, '/')) {
		$seperator = '/';
	} else {
		return false;
	}

	$hour = $minute = 0;

	if ($incTime) {
		$parts = explode(' ', $date);
		if (count($parts) == 3) {
			$date = $parts[0];
			$time = explode(':', $parts[1]);
			$hour = $time[0];
			$minute =$time[1];
			$meridiem = strtolower($parts[2]);
			if ($meridiem == 'pm' && $hour < 12 ) {
				$hour = $hour + 12;
			}
			else if($meridiem == 'am' && $hour == 12) {
				$hour = 0;
			}
		}
	}

	$parts = explode($seperator, $date);
	$unixDate = mktime($hour, $minute, 0, $parts[1], $parts[0], $parts[2]);

	return $unixDate;
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
	return eregi("^[\w]+([\w\.\-]+)*@[\w]+(\.[\w]+)*(\.[a-z]{2,3})$",$email);
}

function outputCSV($line, $delimiter=',', $enclosure='"') {
   // No leading delimiter
   $writeDelimiter = FALSE;

   foreach($line as $dataElement){
	   // Replaces a double quote with two double quotes
	   $dataElement=str_replace("\"", "\"\"", $dataElement);

	   // Adds a delimiter before each field (except the first)
	   if($writeDelimiter) $string .= $delimiter;

	   // Encloses each field with $enclosure and adds it to the string
	   $string .= $enclosure . $dataElement . $enclosure;

	   // Delimiters are used every time except the first.
	   $writeDelimiter = TRUE;
   }
	return $string;
}

function convertFromURL($string)
{
	 if (get_magic_quotes_gpc()) {
			 $string = stripslashes($string);
	}

	//Assumption: no '-' in names all first chars are capitals
	//$temp = str_replace('and','%26',$string);

	return urldecode(str_replace(array('-','^'), array(' ','&'), $string));

}

function convertToURL($string)
{
	return strtolower(preg_replace(array("/[^a-zA-Z\d\s\-\+\/]*/", "/(\s|-|\/)+/", "/\+/"), array("", "-", "p"), trim($string)));
}

function cutString($content, $chars) {
	/* cut a string to the character limit and append ... */
	$summary = substr($content, 0, $chars);
	$length = strlen($content);

	return $summary . (strlen($content) > strlen($summary) ? ".." : "");

}

function shortenName($name, $length) {
	/* shorten a string by replacing a portion of it with '...' */
	$currentLength = strlen($name);
	if ($currentLength <= $length)
		return $name;
	else {
		$cutOut = $currentLength - $length + 3;
		$endPoint = $currentLength - ceil(($currentLength - $cutOut) * 0.25);
		$startPoint = ceil($endPoint - $cutOut);

		return substr($name, 0, $startPoint) . '...' . substr($name, $endPoint);
	}
}

function cutSentence($content, $chars) {
	/* Cut off a summary at the fullstop within the character limit, alternatively just at the word */
	if(strlen($content) < $chars)
		return $content;

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


function delfile($str)
{
    foreach(glob($str) as $fn) {
        unlink($fn);
    }
}

function isLocalURL($url)
{
	//returns true if the url is for a local page
    list($hostname, $domain) = explode('.',$_SERVER['HTTP_HOST'],2);

    $pattern = '#^(http(s)?://)('.$hostname.')|('.$_SERVER['HTTP_HOST'].')|('.Cfg::get('site_hostname').'(.'.Cfg::get('site_domain').'))/(.*)?#i';

    return preg_match($pattern, $url);

}

function relativeURL($url) {
    list($hostname, $domain) = explode('.',$_SERVER['HTTP_HOST'],2);

	/* Takes into account subdomain name e.g. ilearn/ and ilearn.joeys
	 * (that originally caused the issue with links not being correct)
	 */
    $hosts = array(
        'http://' . Cfg::get('site_hostname') . '.' . Cfg::get('site_domain'),
        'https://' . Cfg::get('site_hostname'). '.' . Cfg::get('site_domain'),
        'http://' . $_SERVER['HTTP_HOST'],
        'https://' . $_SERVER['HTTP_HOST'],
        'http://' . Cfg::get('site_hostname'),
        'https://' . Cfg::get('site_hostname'),
    );

    return str_replace($hosts, '', $url);
}

function formatName($userInfo, $format = NULL){
	// Check for custom format
	if(empty($format)){
		$format = Cfg::get('name_format');
	}

    /* Replace tags */
    preg_match_all('|{\w+}|', $format, $tags);

    if (empty($tags) || empty($userInfo)) {
        return 'Unknown User';
    }

    foreach ($tags[0] as $tag) {
        $key = trim($tag, '{}');

        if (is_string($userInfo[$key])) {
            $value = $userInfo[$key];
        } else {
            $value = '';
        }

        $format = str_replace($tag, $value, $format);
    }
    if(trim($format))
        return $format;
    else
        return 'Unknown User';
}

function formatEmail($email) {
	if($email['local_email']) {
			return $email['username']."@".Cfg::get('mail_address');
	}
	else if($email['alt_email'])
		return $email['alt_email'];
	else
		return false;
}

function isUrl($url) {
	$cmp = @parse_url($url);
	if (is_array($cmp) && isset($cmp['host'])){
		return true;
	}
	return false;
}

if (!function_exists('json_encode'))
{
  function json_encode($a=false)
  {
    if (is_null($a)) return 'null';
    if ($a === false) return 'false';
    if ($a === true) return 'true';
    if (is_scalar($a))
    {
      if (is_float($a))
      {
        // Always use "." for floats.
        return floatval(str_replace(",", ".", strval($a)));
      }

      if (is_string($a))
      {
        static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
        return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
      }
      else
        return $a;
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a))
    {
      if (key($a) !== $i)
      {
        $isList = false;
        break;
      }
    }
    $result = array();
    if ($isList)
    {
      foreach ($a as $v) $result[] = json_encode($v);
      return '[' . join(',', $result) . ']';
    }
    else
    {
      foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
      return '{' . join(',', $result) . '}';
    }
  }
}

function plural($num) {
	if ($num != 1)
		return "s";
}

function getRelativeTime($date, $now = 0, $prefix = 'on', $suffix = 'ago') {

	if(!$now)
		$now = time();

	if(!is_numeric($date))
		$date = strtotime($date);

	$diff = $now - $date;

	if ($diff<60)
		return $diff . " second" . plural($diff) . " " . $suffix;
	$diff = round($diff/60);
	if ($diff<60)
		return $diff . " minute" . plural($diff) . " " . $suffix;
	$diff = round($diff/60);
	if ($diff<24)
		return $diff . " hour" . plural($diff) . " " . $suffix;
	$diff = round($diff/24);
	if ($diff<2)
		return ' Yesterday';
	if ($diff<7)
		return $diff . " days " . $suffix;
	$diff = round($diff/7);
	if ($diff<2)
		return ' Last week';
	if ($diff<4)
		return $diff . " weeks " . $suffix;
	return $prefix.' '.date(Cfg::get('date_notime'), $date);
}


function relativeLinkRegex($url) {
	/* Local url:
	 * http://ilearn
	 * http://ilearn/
	 * http://ilearn/test/blah
	 */
	if (preg_match('#^(http://|https://)([^\.]+)/(.*)?#i', $url, $matches) || preg_match('#^(http://|https://)([^\.]+)$#i', $url, $matches)) {

		/* Remove unused matches
		 */
		unset($matches[0]);
		unset($matches[1]);

		/* Remove empty elements
		 */
		foreach(array_keys($matches, '') as $element) {
			unset($matches[$element]);
		}

		$url = '/' . implode('/', $matches);
	}

	/* Local url:
	 * //ilearn
	 * //ilearn/test/blah
	 */
	else if (preg_match('#^//(.*)?#i', $url, $matches)) {
		$url = $matches[0];
	}

	/* Local url:
	 * ilearn
	 * ilearn/test/blah
	 */
	else if (preg_match('#^([^\.]+)$#', $url, $matches)) {
		$url = '/' . $matches[0];
	}

	/* External url:
	 * google.com
	 * www.google.com
	 * http://www.google.com
	 * https://www.google.com
	 */
	else {
		/* Normal modification to url
		 * Just return url if it includes protocol
		 * Otherwise add http protocol if it was not found
		 */
		if (!preg_match('#http(s?)://(.*)#i', $url, $matches)) {
			$url = 'http://' . $url;
		}
	}

	return $url;
}

/**
 * Converts component-based date/time into database-friendly format
 *
 * @param  $date
 * @param  $hour
 * @param  $minute
 * @param  $period
 * @return string
 */
function getDateTimeByComponents($date, $hour, $minute, $period) {
	return date(Cfg::get('date_database'), strtotime(scrubTEXT(str_replace('/', '-', $date) . " {$hour}:{$minute}{$period}")));
}
?>
