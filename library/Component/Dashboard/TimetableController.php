<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_TimetableController extends Component_Dashboard_ControllerAbstract {

    private $externalTimetable;

    private $dayNumber;

    private $currentPeriod;

    private $timetable;

    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage, External_Timetable $externalTimetable) {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
    }

    public function execute () {

        if ( $_SESSION['timetable'] && Cfg::get('external_enable') ) {

            $timetableHandler = $this->externalTimetable;

            //get current day and period
            $this->dayNumber = $timetableHandler->getDayNumber($_SESSION['campus'][0]);
            $this->currentPeriod = $timetableHandler->getPeriodNumber($_SESSION['campus'][0]);

            $this->timetable = $timetableHandler->getDayTimetable($_SESSION['sid'], $_SESSION['id'], $_SESSION['campus'][0], $this->dayNumber);

			return true;
        }
		else {
			return false;
		}

    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'timetable') ) {
            return false;
        }

        $this->setBlock($templateHandle, "timetable_period", "timetable_period_block");
        $this->setBlock($templateHandle, "timetable_header", "timetable_header_block");

        $this->setVar("DAYNUMBER", $this->dayNumber);

        $periodCount = 0;

        if ( is_array($this->timetable) && !empty($this->timetable)) {

            //for each period set result
            foreach ( $this->timetable as $period ) {
                if ( $period['periodSequence'] == 1 ) {
                    $this->setVar("PERIOD_NAME", $period['periodName']);
                    $this->setVar("PERIOD_START", $period['periodStart']);
                    $this->setVar("PERIOD_END", $period['periodEnd']);
                    $periodCount++;

                    if ( $period['description'] ) {
                        //custom stuff for grimwade
                        if ( $_SESSION['campus'][0] == 3 && Cfg::get('site_location') == 'mgs' ) {
                            $post = strpos($period['code'], "EN");

                            if ( $post == 2 ) {
                                $this->setVar('PERIOD_DESC', 'Homeroom');
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', '');
                            }
                            else {
                                $this->setVar('PERIOD_DESC', $period['description']);
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', $period['room']);
                            }

                            $this->setVar('PERIOD_ABSENTEES', '');
                        }
                        else {
                            if ( $_SESSION['campus'][0] == 2 && Cfg::get('site_location') == 'wenona' ) {
                                $this->setVar('PERIOD_DESC', $period['description']);
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', $period['room']);
                                $this->setVar('PERIOD_ABSENTEES', '');
                            }
                            else {
                                $this->setVar('PERIOD_DESC', "<a href=\"/homepage/" . urlencode($period['code']) . "\">{$period['description']}</a>");
                                $this->setVar('PERIOD_CODE', '(' . $period['code'] . ')');
                                $this->setVar('PERIOD_ROOM', $period['room']);

                                //only show attendances to staff at mgs
                                /*if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
                                   $this->setVar('PERIOD_ABSENTEES',"<br /><a href=\"/absentees/attendances.php?code=".$period['code']."\">attendances</a>");
                               else */
                                $this->setVar('PERIOD_ABSENTEES', '');

                            }
                        }

                    }
                    else {
                        $this->setVar('PERIOD_DESC', '');
                        $this->setVar('PERIOD_CODE', '');
                        $this->setVar('PERIOD_ROOM', '');
                        $this->setVar('PERIOD_ABSENTEES', '');
                        $this->setVar('PERIOD_ONCLICK', '');
                    }

                    if ( $this->currentPeriod['PeriodNumber'] == $period['periodNumber'] ) {
                        $this->setVar("PERIOD_CLASS", "current");
                    }
                    else {
                        $this->setVar("PERIOD_CLASS", "normal");
                    }

                    //output period block
                    $this->parse("timetable_header_block", "timetable_header", true);
                    $this->parse("timetable_period_block", "timetable_period", true);
                }
            }

            //timetable field width
            $this->setVar("TIMETABLE_FIELD_WIDTH", floor(( ( 98 - ( $periodCount * 0.05 ) ) / $periodCount )));

            return true;
        }
        else {
            return false;
        }
    }

}
