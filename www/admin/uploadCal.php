<?php
// Portal Page to process bulk upload of calander information.
// original version jo treweek
// written by luke cawood
// last updated 13/1/05 - added ability to upload as someone else

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("../include/permission.php");
require_once("../../library/calendar.php");

ini_set('auto_detect_line_endings', true);


class uploadCal {

    function displayPage($response = "")
    {
        //nothing has been submitted
        //display input page..

        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "admin/uploadCal.htm");
        $this->t_body->set_var("TITLE", HEADING." - Upload Campus Calendar Infomation");
        $this->t_body->set_block("tplBODY", "user_group_check_list", "user_group_chck_lst");
        $this->t_body->set_block("tplBODY", "campus_check_list", "campus_chck_lst");

        /* Header variables
         */
        $this->t_body->set_var('SITE_GREETING', greeting());
        $this->t_body->set_var('SITE_LOGINNAME', formatName($_SESSION));

        /* Footer variables
         */
        $this->t_body->set_var('TEMPLATE_YEAR', date('Y'));
        $this->t_body->set_var('SITE_NAME', SITE_name);

        $db = new DeprecatedDatabase();
        $query = "SELECT * FROM luCampus ORDER BY name";
        $db->query($query);
        while($thisCampus = $db->getNextArray())
        {
            $this->t_body->set_var("CAMPUSNAME", $thisCampus['name']);
            $this->t_body->set_var("CAMPUSID", $thisCampus['id']);
            $this->t_body->parse("campus_chck_lst", "campus_check_list", true);
        }

        $db = new DeprecatedDatabase();
        $query = "SELECT * FROM groups ORDER BY name";
        $db->query($query);
        while($thisCampus = $db->getNextArray())
        {
            $this->t_body->set_var("USERGROUPNAME", $thisCampus['name']);
            $this->t_body->set_var("USERGROUPID", $thisCampus['id']);
            $this->t_body->parse("user_group_chck_lst", "user_group_check_list", true);
        }
        include_once("../include/page.php");
        $this->t_body->set_var("UPLOADRESULTS", $response);
        $this->t_body->pparse("finaloutput", "tplBODY");

    }

    function processCal()
    {

        $db = new DeprecatedDatabase();
        $calendarHandler = new Calendar();
        $cal = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$cal)
        {
            die("unable to open file");
        }

        //set defaults for id and campus if they are not submitted
        if($_POST['userid'] == "")
            $uid = $_SESSION['id'];
        else
            $uid = $_POST['userid'];

        if($_POST['campus'] == "")
            $campus = $_SESSION['campus'][0];
        else
            $campus = $_POST['campus'];
        $output	= "Processing CSV file<br>";
        while(!feof($cal))
        {

            list($date,$stime,$ftime,$comment,$type) = fgetcsv($cal,4096);

            $comment = trim($comment);

            # Skip empty comments and no dates
            if($comment && $date)
            {
                $event = array(	'owner'		=> $uid,
                                'access'	=> 3,
                                'type'		=> $type,
                                'note'		=> $comment,
                                'date'		=> $date,
                                'startTime'	=> $stime,
                                'endTime'	=> $ftime,
                                'groupList'	=> $_POST['userGroup'],
                                'campusList'=> $_POST['campus'],
                                );

                if($calendarHandler->create($event) )
                    $output .= "<div style=\"color: #66CC33;\">Successfully added event '$comment'<br /></div>";
                else
                    $output .= "<div style=\"color: #CC3300;\">Failed to add event '$comment'<br /></div>";

            }
        }
        $this->displayPage($output);
    }

    function processVCal() {


        require_once dirname(__FILE__) .'/../include/iCalcreator.class.php';

        if($_POST['userid'] == "")
            $uid = $_SESSION['id'];
        else
            $uid = $_POST['userid'];

        $v = new vcalendar();
        $cal = new Calendar;

        $v->setConfig('unique_id', Cfg::get('mail_address') );

        $v->setProperty('calscale', 'GREGORIAN');
        $v->setProperty('method', 'PUBLISH');

        $v->setProperty( 'X-WR-TIMEZONE', 'Australia/Melbourne' );

        date_default_timezone_set('Australia/Melbourne');

        /* start parse of local file */
        //$v->setConfig( 'directory', 'calendar' );
        // set directory
        //$v->setConfig( 'filename', $_FILES['file']['tmp_name'] );
        // set file name
        $v->parse($_FILES['file']['tmp_name']);

        $eventArray = $v->selectComponents(date('Y'), 01, 01, date('Y')+1,12,31);
        //print_r($eventArray);
        $output = "Processing VCAL files<br>";
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

                        $event = array('owner' 		=> $uid,
                                       'access'		=> 3,
                                       'groupList'	=> $_POST['userGroup'],
                                       'campusList' => $_POST['campus'],
                                       'type'		=> 1,
                                       'note'		=> $summary,
                                       'startTime'	=> isset($dtstart['hour'])?date('H:i:s', $stime):'',
                                       'endTime'	=> isset($dtend['hour'])?date('H:i:s', $etime):'',
                                       'date'       => date('Y-m-d', $stime),
                                      );

                        if($cal->create($event) )
                            $output .= "<div style=\"color: #66CC33;\">Successfully added event '$summary'<br /></div>";
                        else
                            $output .= "<div style=\"color: #CC3300;\">Failed to add event '$summary'<br /></div>";
                    }
                }
            }
        }

         $this->displayPage($output);
    }
}

$page = new uploadCal;

if ($_FILES['file']['tmp_name'])
{
    if(stristr(file_get_contents($_FILES['file']['tmp_name'],NULL, NULL, 0, 20),'BEGIN:VCALENDAR'))
        $page->processVCal();
    else
        $page->processCal();
}else
{
	$page->displayPage();
}
?>
