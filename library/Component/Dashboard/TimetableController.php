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

    private $colourList;

    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, Group $group, External_Timetable $externalTimetable) {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
        $this->group = $group;
    }

    public function execute() {

        if (!($_SESSION['timetable'] && Cfg::get('external_enable'))) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;

        //get current day and period
        $this->dayNumber = $timetableHandler->getDayNumber($_SESSION['campus'][0]);
        $this->currentPeriod = $timetableHandler->getPeriodNumber($_SESSION['campus'][0]);

        $this->timetable = $timetableHandler->getDayTimetable(
            $_SESSION['sid'], $_SESSION['id'], $_SESSION['campus'][0], $this->dayNumber
        );

        $this->colourList = Timetable_ColourMapper::getInstance()->findAll();

        return true;


    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'timetable')) {
            return false;
        }

        $this->setBlock($templateHandle, "timetable_period", "timetable_period_block");
        $this->setBlock($templateHandle, "timetable_header", "timetable_header_block");
        $this->setBlock('timetable_period', 'has_attendances', "has_attendances_block");

        $this->setVar("DAYNUMBER", $this->dayNumber);

        $periodCount = 0;

        if (is_array($this->timetable) && !empty($this->timetable)) {
            /* Setup colour map */
            $subjectColourMap = array();
            $colourIndex = 0;

            //for each period set result
            foreach ($this->timetable as $period) {
                if ($period['periodSequence'] == 1) {
                    $this->setVar("PERIOD_NAME", $period['periodName']);
                    $this->setVar("PERIOD_START", $period['periodStart']);
                    $this->setVar("PERIOD_END", $period['periodEnd']);
                    $periodCount++;

                    if ($period['description']) {

                        //custom stuff for grimwade
                        if ($_SESSION['campus'][0] == 3 && Cfg::get('site_location') == 'mgs') {
                            $post = strpos($period['code'], "EN");

                            if ($post == 2) {
                                $this->setVar('PERIOD_DESC', 'Homeroom');
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', '');
                            } else {
                                $this->setVar('PERIOD_DESC', $period['description']);
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', $period['room']);
                            }

                            $this->setVar('PERIOD_ABSENTEES', '');
                        } else {
                            if ($_SESSION['campus'][0] == 2 && Cfg::get('site_location') == 'wenona') {
                                $this->setVar('PERIOD_DESC', $period['description']);
                                $this->setVar('PERIOD_CODE', '');
                                $this->setVar('PERIOD_ROOM', $period['room']);
                                $this->setVar('PERIOD_ABSENTEES', '');
                            } else {
                                $this->setVar(
                                    'PERIOD_DESC', "<a href=\"/homepage/" . urlencode(
                                    $period['code']
                                ) . "\">{$period['description']}</a>"
                                );
                                $this->setVar('PERIOD_CODE', '(' . $period['code'] . ')');
                                $this->setVar('PERIOD_ROOM', $period['room']);

                            }
                        }

                        // Link to Attendance Records
                        if (strlen($period['code']) && Cfg::get('external_type') == 'synergetic' && Cfg::get(
                            'external_attendance'
                        ) && $this->group->isStaff()
                        ) {
                            $now = new Alaress_Date_Format(time(), 'U');

                            $this->setVar(
                                array(
                                    'ATTENDANCE_CLASS_CODE' => $period['code'],
                                    'ATTENDANCE_ATTENDANCE_DATE' => $now->getDatabaseDate(),
                                    'ATTENDANCE_ATTENDANCE_PERIOD' => $period['periodNumber'],
                                )
                            );

                            $this->parse('has_attendances_block', 'has_attendances');
                        } else {
                            $this->setVar('has_attendances_block', '');
                        }

                        /* Handle colour array */
                        if ($period['code'] && !array_key_exists($period['code'], $subjectColourMap)) {
                            $subjectColourMap[$period['code']] = $this->colourList[$colourIndex]->hex_colour;
                            ++$colourIndex;
                        }

                        $this->setVar('PERIOD_COLOUR', '#' . $subjectColourMap[$period['code']]);

                    } else {
                        $this->setVar(
                            array(
                                'PERIOD_COLOUR' => '',
                                'PERIOD_DESC' => '',
                                'PERIOD_CODE' => '',
                                'PERIOD_ROOM' => '',
                                'PERIOD_ABSENTEES' => '',
                                'PERIOD_ONCLICK' => '',
                                'has_attendances_block' => '',
                            )
                        );


                    }

                    if ($this->currentPeriod['PeriodNumber'] == $period['periodNumber']) {
                        $this->setVar("PERIOD_CLASS", "current");
                    } else {
                        $this->setVar("PERIOD_CLASS", "normal");
                    }


                    //output period block
                    $this->parse("timetable_header_block", "timetable_header", true);
                    $this->parse("timetable_period_block", "timetable_period", true);

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar('has_attendance_block', '');
                }
            }

            //timetable field width
            $this->setVar("TIMETABLE_FIELD_WIDTH", floor(((98 - ($periodCount * 0.05)) / $periodCount)));

            return true;
        } else {
            return false;
        }
    }

}
