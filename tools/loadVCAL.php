<?php


require_once dirname(__FILE__) . '/../www/conf/conf.php';
require_once dirname(__FILE__) . '/../include/ICal/iCalcreator.class.php';
require_once dirname(__FILE__) . '/../library/calendar.php';

$v = new vcalendar();
$cal = new Calendar;

$v->setConfig('unique_id', Cfg::get('mail_address') );

$v->setProperty('calscale', 'GREGORIAN');
$v->setProperty('method', 'PUBLISH');

$v->setProperty( 'X-WR-CALNAME', HEADING );          // set some X-properties, name, content.. .
$v->setProperty( 'X-WR-CALDESC', 'The portal school calendar' );
$v->setProperty( 'X-WR-TIMEZONE', 'Australia/Melbourne' );

date_default_timezone_set('Australia/Melbourne');

/* start parse of local file */
$v->setConfig( 'directory', 'calendar' );
// set directory
$v->setConfig( 'filename', $argv[1] );
// set file name
$v->parse();

$eventArray = $v->selectComponents(2010, 01, 01, 2010,12,31);
//print_r($eventArray);

foreach( $eventArray as $year => $yearArray) {
	foreach( $yearArray as $month => $monthArray ) {
		foreach( $monthArray as $day => $dailyEventsArray ) {
			foreach( $dailyEventsArray as $vevent ) {
				$currddate = $vevent->getProperty( 'x-current-dtstart' );
				// if member of a recurrence set,
				// returns array( 'x-current-dtstart', <DATE>)
				// <DATE> = (string) date("Y-m-d [H:i:s][timezone/UTC offset]")
				$dtstart = $vevent->getProperty( 'dtstart' );
                $duration = $vevent->getProperty( 'duration' );
                $dtend = $vevent->getProperty( 'dtend' );
				// dtstart required, one occurence, (orig. start date)
				$summary = $vevent->getProperty( 'summary' );
				$description = $vevent->getProperty( 'description' );
	
                $hour = $dtstart['hour']?$dtstart['hour']:'00';
                $min = $dtstart['min']?$dtstart['min']:'00';
                $stime = strtotime($dtstart['year'].$dtstart['month'].$dtstart['day'].'T'.$hour.$min.'00Z');
                $hour = $dtend['hour']?$dtend['hour']:'00';
                $min = $dtend['min']?$dtend['min']:'00';
                $etime = strtotime($dtend['year'].$dtend['month'].$dtend['day'].'T'.$hour.$min.'00Z');

                $event = array('owner' 		=> 1,
                               'access'		=> 3,
                               'campusList'	=> 1,
                               'groupList'	=> array(1,2,3,4,5),
                               'type'		=> 1,
                               'note'		=> $summary,
                               'startTime'	=> isset($dtstart['hour'])?date('H:i:s', $stime):'',
                               'endTime'	=> isset($dtend['hour'])?date('H:i:s', $etime):'',
                               'date'       => date('Y-m-d', $stime),
                              );
              $cal->create($event);
			}
		}
	}
}


?>
