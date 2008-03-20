<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2008 Lektec Consulting         *
*                                                           *
*							                                *
*  Script to export portal campus calendar events to vCal.  *
*         				                                    *
*							                                *
/************************************************************/

require_once('../include/include.php');
require_once('../include/iCalcreator.class.php');
require_once('calendar.php');

$v = new vcalendar();
$cal = new Calendar;

$v->setConfig('unique_id', MAIL_address ); 

$v->setProperty('calscale', 'GREGORIAN');
$v->setProperty('method', 'PUBLISH');

$v->setProperty( 'X-WR-CALNAME', HEADING );          // set some X-properties, name, content.. .
$v->setProperty( 'X-WR-CALDESC', 'The portal school calendar' );
$v->setProperty( 'X-WR-TIMEZONE', 'Australia/Melbourne' );

$t = new vtimezone();
$t->setProperty( 'tzid', 'Australia/Melbourne');
$t->setProperty( 'last-modified', 1987, 1, 1 );

$ts = new vtimezone( 'standard' );
$ts->setProperty( 'dtstart' , 1997, 10, 26, 2 );
$rdate1 = array ( 'year' => 1997, 'month' => 10, 'day' => 26, 'hour' => 02, 'min' => 0, 'sec' => 0 );
$ts->setProperty( 'rdate', array( $rdate1 ));
$ts->setProperty( 'tzoffsetfrom', '+1100' );
$ts->setProperty( 'tzoffsetto', '+1000' );
$ts->setProperty( 'tzname', 'EST' );
$t->setComponent( $ts );

$td = new vtimezone( 'daylight' );
$td->setProperty( 'dtstart', 1997, 10, 26, 2 );
$rdate1 = array ( 'year' => 1997, 'month' => 4, 'day' => 6, 'hour' => 02, 'min' => 0, 'sec' => 0 );
$td->setProperty( 'rdate', array( $rdate1 ));
$td->setProperty( 'tzoffsetfrom', '+1000' );
$td->setProperty( 'tzoffsetto', '+1100' );
$td->setProperty( 'tzname', 'EST' );
$t->setComponent( $td );

$v->setComponent( $t );


$year = date('Y');

for($x=1; $x < 364; $x++) {
	
	$date = mktime(5,0,0,0,$x,$year);
 	$month = date('n', $date);
	$day = date('j', $date);
 
	$events = $cal->getAllDayEvents($day, $month, $year, 1, 3);
	
	if(is_array($events)) {
		foreach($events as $event) {
			
			
			$e = new vevent();                             
			$e->setProperty('categories', 'SCHOOL' );  
			
			                 
			if($event['startTimestamp']) {
				$startHour = floor($event['startTimestamp'] / 60 / 60);
				$startMinute = ($event['startTimestamp'] /60) % 60;
				$e->setProperty('dtstart', $year, $month, $day, $startHour, $startMinute,array('VALUE'=>'DATE-TIME') );
				$e->setProperty('duration', 0, 0, 1, 0 );
			}   
			else
				$e->setProperty('dtstart', $year, $month, $day,FALSE,FALSE,array('VALUE'=>'DATE'));
			
			$e->setProperty('summary', $event['note'] ); 
			$v->addComponent( $e );                        
		}
	}
}

$str = $v->createCalendar();
echo $str;
echo "<br />\n\n";
?>
