<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2003-2004 Lektec Consulting         *
 *                                                           *
 *    This page fills out the content on the standard portal    *
 *    template.  Tabs, drop down menu, groups, subjects are    *
 *    all populated from this code.                             *
 *   $Id: page.php,v 1.19 2004/04/27 05:16:48 jleckie Exp $
 *                                                           *
 ************************************************************/

require_once($_SERVER['DOCUMENT_ROOT'] . "/include/permission.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/include/dbManager-mysql.php");

//recycle any existing db class else continue (should also check it is the write object)
if (isset($this->db)) {
    $db = $this->db;
} else {
    if (!isset($db)) {
        $db = new dbManager;
        $db->dbConnect();
    }
}

$this->t_body->set_var('TEMPLATE_FILE', $this->templateFile);
$this->t_body->set_var('YEAR', date('Y'));

//this section handles the drop down menu
if ($_SESSION['su']) {
    $query = "SELECT sideMenu.id, parent, txt, url, target FROM sideMenu ORDER BY parent, sequence, txt";
} else {
    $query = "SELECT DISTINCT(sideMenu.id), parent, txt, url, target FROM sideMenu, sideMenuCampus, sideMenuGroup
          WHERE (sideMenu.id = sideMenuCampus.id AND sideMenuCampus.campus IN (" . implode(',', $_SESSION['campus']) . ") )
            AND (sideMenu.id = sideMenuGroup.id AND sideMenuGroup.gid = '" . $_SESSION['gid'] . "')
          ORDER BY parent, sequence, txt";
}

$db->dbDoQuery($query);

//initialise root of tree
$list[0]['id'] = 0;

//fetch each record and insert into tree
while ($menuitem = $db->dbGetNextArray()) {
    $list = insertChild($list, $menuitem);
}

//output tree with html
$this->t_body->set_var("MCSSMENU", outputMenu($list[0]['children'], 1));

//this section handles the mylinks section in the template

//setup block
$this->t_body->set_block("tplBODY", "mlink_block", "my_links");
$query = "SELECT url, description FROM bookmarks WHERE owner = '" . $_SESSION['id'] . "'";
$db->dbDoQuery($query);
if ($db->dbCountRows() > 0) {
    while ($link = $db->dbGetNextArray()) {
        if (isLocalURL($link['url'])) {
            $this->t_body->set_var(
                "MLINK", "<a href=\"" . $link['url'] . "\"><span>" . scrubTEXT($link['description']) . "</span></a>"
            );
        } else {
            /* Why did this double-urlencode? Removed.
             */
            $this->t_body->set_var(
                "MLINK",
                "<a href=\"/redir.php?url=" . urlencode($link['url']) . "\" target=\"_blank\"><span>" . scrubTEXT(
                    $link['description']
                ) . "</span></a>"
            );
        }

        $this->t_body->parse("my_links", "mlink_block", true);
    }
} else {
    $this->t_body->set_var("my_links", "");
}

//this section handle mygroups section in the template
$this->t_body->set_block("tplBODY", "mgroup", "mgroup_section");
$this->t_body->set_block("mgroup", "mgroup_block", "my_groups");

$query = "SELECT category.name, userAccess.sid, userAccess.type
		  FROM userAccess, category
		  WHERE userAccess.uid = '" . $_SESSION['id'] . "'
		  AND userAccess.sid = category.id
		  AND userAccess.type > 0
		  AND category.template <> 0
	";
$db->dbDoQuery($query);
if ($db->dbCountRows() > 0) {

    while ($group = $db->dbGetNextArray()) {
        $this->t_body->set_var("MGNAME", scrubTEXT($group['name']));
        $this->t_body->set_var("MGID", $group['sid']);
        $this->t_body->parse("my_groups", "mgroup_block", true);
    }
    $this->t_body->parse("mgroup_section", "mgroup");
} else {
    $this->t_body->set_var("mgroup_section", "");
}


//this section handles the mysubjects section in the template
$this->t_body->set_block("tplBODY", "msubject", "msubject_section");
$this->t_body->set_block("msubject", "msubject_block", "my_subjects");

if (is_array($_SESSION['subjects'])) {
    foreach ($_SESSION['subjects'] as $subject) {
        if (isStaff($_SESSION['gid'])) {
            $this->t_body->set_var("MSUBJECT", $subject['name'] . " (" . $subject['code'] . ")");
        } else {
            $this->t_body->set_var("MSUBJECT", $subject['name']);
        }

        $this->t_body->set_var("MSUBCODE", urlencode($subject['code']));
        $this->t_body->parse("my_subjects", "msubject_block", true);
    }
    $this->t_body->parse("msubject_section", "msubject");
} else {
    $this->t_body->set_var("msubject_section", "");
}


//my students section
$this->t_body->set_block("tplBODY", "mstudent", "mstudent_section");
$this->t_body->set_block("mstudent", "mstudent_block", "my_students");

if (is_array($_SESSION['children'])) {
    foreach ($_SESSION['children'] as $child) {
        $query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
        $user = $db->dbGetSingleRow($query);
        $this->t_body->set_var("MSTUDENT_ID", $child['id']);
        $this->t_body->set_var("MSTUDENT_NAME", formatName($user, '{firstname}'));
        $this->t_body->set_var("MSTUDENT_YEAR", $user['year']);

        $this->t_body->parse("my_students", "mstudent_block", true);

    }
    $this->t_body->parse("mstudent_section", "mstudent");
} else {
    $this->t_body->set_var("mstudent_section", "");
}


//this section hanldes the display of the timetable tab
$this->t_body->set_block('tplBODY', 'timetable_tab', 'tt_tab');
//if the have a timetable or their children have a timetable
if ($_SESSION['timetable'] || is_array($_SESSION['children'][0]['subjects'])) {
    $this->t_body->parse('tt_tab', 'timetable_tab');
} else {
    $this->t_body->set_var('tt_tab', '');
}

//this section handles the display of the webmail tab
// if configuration states mail is available and user has access to local mail account display it
$this->t_body->set_block('tplBODY', 'webmail_tab', 'wm_tab');

if ((MAIL_enable && $_SESSION['local_email']) || (SITE_location == 'wenona' && (isStaff($_SESSION['gid']) || isStudent(
    $_SESSION['gid']
))) || SITE_location == 'ormond' || SITE_location == 'shore'
) {
    $this->t_body->parse('wm_tab', 'webmail_tab');

    if (SITE_location == 'mgs') {
        if (isStaff($_SESSION['gid'])) {
            $this->t_body->set_var(
                "WEBMAIL_LOCATION", "href=\"https://webmail.mgs.vic.edu.au/exchange/\" target=\"_blank\""
            );
        } else {
            $this->t_body->set_var("WEBMAIL_LOCATION", 'href="/mail/"');
        }
    } else {
        if (SITE_location == 'ormond') {
            if ($_SESSION['exchange']) {
                $this->t_body->setVar("WEBMAIL_LOCATION", 'href="http://owa.ormond.unimelb.edu.au" rel="external"');
            } else {
                $this->t_body->set_var('WEBMAIL_LOCATION', 'href="/mail/"');
            }
        } else {
            if (SITE_location == 'wenona') {
                if (isStaff($_SESSION['gid'])) {
                    $this->t_body->setVar(
                        'WEBMAIL_LOCATION', 'href="https://webmail.wenona.nsw.edu.au/owa" rel="external"'
                    );
                } else {
                    $this->t_body->setVar('WEBMAIL_LOCATION', 'href="/mail/"');
                }
            } else {
                if (SITE_location == 'shore') {
                    if (isStaff($_SESSION['gid'])) {
                        $this->t_body->setVar('WEBMAIL_LOCATION', 'href="http://shorexch01/exchange" rel="external"');
                    } else {
                        if (isStudent($_SESSION['gid'])) {
                            $this->t_body->setVar(
                                'WEBMAIL_LOCATION',
                                'href="https://mail.student.shore.nsw.edu.au/exchange/" rel="external"'
                            );
                        }
                    }
                }
            }
        }
    }

} else {
    $this->t_body->set_var('wm_tab', '');
}

//custom code for each site is performed here
if (SITE_location == 'wenona') {
    //provide encoded login details for intranet
    $this->t_body->set_var(
        "ENC_LOGIN", base64_encode(
            xorEncrypt(
                INTRANET_encryption_key, $_SESSION['username'] . ' ' . $_SESSION['password'] . ' ' . date('Y m d H i')
            )
        )
    );

    $this->t_body->set_block('tplBODY', 'portfolio_tab', 'pf_tab');
    $this->t_body->set_block('tplBODY', 'myfiles_tab', 'mf_tab');
    if (isStaff($_SESSION['gid'])) {
        $this->t_body->parse('pf_tab', 'portfolio_tab');
        $this->t_body->parse('mf_tab', 'myfiles_tab');
    } else {
        if (isStudent($_SESSION['gid'])) {
            $this->t_body->parse('mf_tab', 'myfiles_tab');
            $this->t_body->set_var('pf_tab', '');
        } else {
            $this->t_body->set_var('pf_tab', '');
            $this->t_body->set_var('mf_tab', '');
        }
    }
}


//function used to create data structure for drop down menu
function insertChild($list, $element) {
    for ($i = 0; $list[$i]; $i++) {
        //if item is parent insert item
        if ($list[$i]['id'] == $element['parent']) {
            $list[$i]['children'][] = $element;
            return $list;
        }

        if (is_array($list[$i]['children'])) {
            $sublist = insertChild($list[$i]['children'], $element);
            $list[$i]['children'] = $sublist;
        }
    }
    return $list;
}

function outputMenu($list, $level) {
    $text = "<ul>\n";

    for ($i = 0; $list[$i]; $i++) {
        if ($list[$i]['target']) {
            $target = " target={$list[$i]['target']}";
        } else {
            $target = '';
        }

        if (is_array($list[$i]['children'])) {
            $text .= '<li class="parent"><a href="' . $list[$i]['url'] . '"' . $target . '><span>' . $list[$i]['txt'] . '</span></a>';
            $text .= outputMenu($list[$i]['children'], ($level + 1));
        } else {
            $text .= '<li><a href="' . $list[$i]['url'] . '"' . $target . '><span>' . $list[$i]['txt'] . '</span></a>';
        }

        $text .= "</li>\n";
    }
    $text .= "</ul>\n";
    return $text;
}


function conType() {
    //detect connection type
    $port = getenv("SERVER_PORT");
    if ($port == 80) {
        $contype = "http://";
    } else {
        if ($port == 443) {
            $contype = "https://";
        }
    }

    return $contype;
}


//Function for redirecting to a specified file
function localRedir($file) {
    $contype = conType();
    header("Location: " . $contype . $_SERVER['HTTP_HOST'] . $file);
    exit;
}

//Function
function greeting() {
    if (date("A") == "AM") {
        $greeting = "Good Morning";
    } else {
        if (date("G") < 17) {
            $greeting = "Good Afternoon";
        } else {
            $greeting = "Good Evening";
        }
    }

    return $greeting;
}

//This function is used when the session is not correct simply redirects
//user to login page with the requested url
function goLoginPage() {
    require_once dirname(__FILE__) . '/../../include/sitePage.php';
    $page = new SitePage;
    $page->goLoginPage();
}

//function for returning user ip (tries to work around issues with proxies)
function getIP() {
    if ($_SERVER['HTTP_CLIENT_IP']) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    else {
        if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            if ($_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = "UNKNOWN";
            }
        }
    }
    return $ip;
}

//converts a size integer to human readable size
function bytesToString($size) {
    $a = array("B", "KB", "MB", "GB", "TB", "PB");

    for ($i = 0; $size >= 1024; $i++) {
        $size /= 1024;
    }

    return round($size, 2) . " " . $a[$i];
}

function scrubHTML($html) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Filter', 'YouTube', true);
    $config->set(
        'Filter', 'Custom', array(
            new HTMLPurifier_Filter_Quicktime(),
            new HTMLPurifier_Filter_Shockwave(),
            new HTMLPurifier_Filter_Flash(),
            new HTMLPurifier_Filter_WindowsMedia(),
            new HTMLPurifier_Filter_Real()
        )
    );

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
 * @param       str             $date           date should be in the format of dd-mm-yyyy or dd/mm/yyyy
 * @param       bool    $incTime        if set, try to get the time from the end of the string
 * @return      int                                     unix timestamp or false if invalid date given
 */
function ausDateToUnix($date, $incTime = false) {

    if (strstr($date, '-')) {
        $seperator = '-';
    } else {
        if (strstr($date, '/')) {
            $seperator = '/';
        } else {
            return false;
        }
    }

    $hour = $minute = 0;

    if ($incTime) {
        $parts = explode(' ', $date);
        if (count($parts) == 3) {
            $date = $parts[0];
            $time = explode(':', $parts[1]);
            $hour = $time[0];
            $minute = $time[1];
            $meridiem = strtolower($parts[2]);
            if ($meridiem == 'pm' && $hour < 12) {
                $hour = $hour + 12;
            } else {
                if ($meridiem == 'am' && $hour == 12) {
                    $hour = 0;
                }
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

    if ($date == '0000-00-00' || $date == '0000-00-00 00:00:00' || $date < 1) {
        return '';
    }

    //if format contains a time check if it is null if so remove the time from the format
    if (strpos($format, ':')) {
        $time = date('g:ia', $date);
        if ($time == '12:00am') {
            $format = preg_replace('/[a-z]:[a-z]+/i', '', $format);
            return date($format, $date);
        }
    }

    return date($format, $date);
}

function isEmail($email) {
    return eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email);
}

function outputCSV($line, $delimiter = ',', $enclosure = '"') {
    // No leading delimiter
    $writeDelimiter = false;

    foreach ($line as $dataElement) {
        // Replaces a double quote with two double quotes
        $dataElement = str_replace("\"", "\"\"", $dataElement);

        // Adds a delimiter before each field (except the first)
        if ($writeDelimiter) {
            $string .= $delimiter;
        }

        // Encloses each field with $enclosure and adds it to the string
        $string .= $enclosure . $dataElement . $enclosure;

        // Delimiters are used every time except the first.
        $writeDelimiter = true;
    }
    return $string;
}

function convertFromURL($string) {
    if (get_magic_quotes_gpc()) {
        $string = stripslashes($string);
    }

    //Assumption: no '-' in names all first chars are capitals
    //$temp = str_replace('and','%26',$string);

    return urldecode(str_replace(array('-', '^'), array(' ', '&'), $string));

}

function convertToURL($string) {
    return strtolower(
        preg_replace(array("/[^a-zA-Z\d\s\-\+\/]*/", "/(\s|-|\/)+/", "/\+/"), array("", "-", "p"), trim($string))
    );
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
    if ($currentLength <= $length) {
        return $name;
    } else {
        $cutOut = $currentLength - $length + 3;
        $endPoint = $currentLength - ceil(($currentLength - $cutOut) * 0.25);
        $startPoint = ceil($endPoint - $cutOut);

        return substr($name, 0, $startPoint) . '...' . substr($name, $endPoint);
    }
}

function cutSentence($content, $chars) {
    /* Cut off a summary at the fullstop within the character limit, alternatively just at the word */
    if (strlen($content) < $chars) {
        return $content;
    }

    $summary = substr($content, 0, $chars);
    $position = strrpos($summary, ".") + 1;
    $positionQ = strrpos($summary, "?") + 1;
    $positionB = strrpos($summary, "!") + 1;
    if ($position > 1 && $position > $positionQ && $position > $positionB) {
        $output = substr($summary, 0, $position);
        return $output;
    } else {
        if ($positionQ > 1 && $positionQ > $positionB) {
            $output = substr($summary, 0, $positionQ);
            return $output;
        } else {
            if ($positionB > 1) {
                $output = substr($summary, 0, $positionB);
                return $output;
            } else {
                $position = strrpos($summary, " ");
                $output = substr($summary, 0, $position);
                if ($output) {
                    return $output . "...";
                } else {
                    return "";
                }
            }
        }
    }
}


function delfile($str) {
    foreach (glob($str) as $fn) {
        unlink($fn);
    }
}

function isLocalURL($url) {
    //returns true if the url is for a local page
    if (strncasecmp($url, "http://" . $_SERVER['HTTP_HOST'], 7 + strlen($_SERVER['HTTP_HOST'])) &&
        strncasecmp($url, "https://" . $_SERVER['HTTP_HOST'], 8 + strlen($_SERVER['HTTP_HOST']))
    ) {
        return false;
    } else {
        return true;
    }
}

function formatName($userInfo, $format = null) {
    // Check for custom format
    if (empty($format)) {
        $format = NAME_format;
    }

    // Replace tags
    foreach ($userInfo as $tagName => $tagValue) {
        $format = str_replace('{' . $tagName . '}', $tagValue, $format);
    }

    return $format;
}

function formatEmail($email) {
    if ($email['local_email']) {
        return $email['username'] . "@" . MAIL_address;
    } else {
        if ($email['alt_email']) {
            return $email['alt_email'];
        } else {
            return false;
        }
    }
}

function isUrl($url) {
    $cmp = @parse_url($url);
    if (is_array($cmp) && isset($cmp['host'])) {
        return true;
    }
    return false;
}

if (!function_exists('json_encode')) {
    function json_encode($a = false) {
        if (is_null($a)) {
            return 'null';
        }
        if ($a === false) {
            return 'false';
        }
        if ($a === true) {
            return 'true';
        }
        if (is_scalar($a)) {
            if (is_float($a)) {
                // Always use "." for floats.
                return floatval(str_replace(",", ".", strval($a)));
            }

            if (is_string($a)) {
                static $jsonReplaces = array(
                    array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
                    array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
                );
                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
            } else {
                return $a;
            }
        }
        $isList = true;
        for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
            if (key($a) !== $i) {
                $isList = false;
                break;
            }
        }
        $result = array();
        if ($isList) {
            foreach ($a as $v) {
                $result[] = json_encode($v);
            }
            return '[' . join(',', $result) . ']';
        } else {
            foreach ($a as $k => $v) {
                $result[] = json_encode($k) . ':' . json_encode($v);
            }
            return '{' . join(',', $result) . '}';
        }
    }
}

function plural($num) {
    if ($num != 1) {
        return "s";
    }
}

function getRelativeTime($date, $now = 0, $prefix = 'on', $suffix = 'ago') {

    if (!$now) {
        $now = time();
    }

    if (!is_numeric($date)) {
        $date = strtotime($date);
    }

    $diff = $now - $date;

    if ($diff < 60) {
        return $diff . " second" . plural($diff) . " " . $suffix;
    }
    $diff = round($diff / 60);
    if ($diff < 60) {
        return $diff . " minute" . plural($diff) . " " . $suffix;
    }
    $diff = round($diff / 60);
    if ($diff < 24) {
        return $diff . " hour" . plural($diff) . " " . $suffix;
    }
    $diff = round($diff / 24);
    if ($diff < 2) {
        return ' Yesterday';
    }
    if ($diff < 7) {
        return $diff . " days " . $suffix;
    }
    $diff = round($diff / 7);
    if ($diff < 2) {
        return ' Last week';
    }
    if ($diff < 4) {
        return $diff . " weeks " . $suffix;
    }
    return $prefix . ' ' . date(DATE_short_no_time, $date);
}

?>
