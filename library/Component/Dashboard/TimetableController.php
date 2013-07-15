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

    private $dayNumber = array();

    private $currentPeriod = array();

    private $timetable = array();

    private $colourList;

    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, Group $group, Category $category, External_Timetable $externalTimetable) {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
        $this->group = $group;
        $this->category = $category;
    }

    public function execute() {

        if (!($_SESSION['timetable'] && Cfg::get('external_enable'))) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;

        //get current day and period
        foreach ($_SESSION['campus'] as $campus_id) {
            $this->dayNumber[$campus_id]     = $timetableHandler->getDayNumber($campus_id);
            $this->currentPeriod[$campus_id] = $timetableHandler->getPeriodNumber($campus_id);
            $this->timetable[$campus_id]     = $timetableHandler->getDayTimetable(
                $_SESSION['sid'], $_SESSION['id'], $campus_id, $this->dayNumber[$campus_id]
            );
        }

        $this->colourList = Timetable_ColourMapper::getInstance()->findAll();

        return true;


    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'timetable')) {
            return false;
        }

        $this->setBlock($templateHandle,    "campus_timetable", "campus_timetable_block");
        $this->setBlock("campus_timetable", "timetable_campus_name", "timetable_campus_name_block");
        $this->setBlock("campus_timetable", "timetable_period", "timetable_period_block");
        $this->setBlock("campus_timetable", "timetable_header", "timetable_header_block");
        $this->setBlock('timetable_period', 'period_class_link', 'period_class_link_block');
        $this->setBlock('timetable_period', 'period_class', 'period_class_block');
        $this->setBlock('timetable_period', 'has_attendances', "has_attendances_block");

        $periodCount = 0;

        $timetable_count = 0;
        foreach ($this->timetable as $timetable) {
            if (is_array($timetable) && !empty($timetable)) {
                $timetable_count++;
            }
        }
        if ($timetable_count === 0) {
            return false;
        }

        $campus_list = array();
        foreach ($this->user->getCampusList() as $campus) {
            $campus_list[$campus['id']] = $campus;
        }

        $this->setVar("campus_timetable_block", '');
        foreach ($this->timetable as $campus_id => $timetable) {

            if (is_array($timetable) && !empty($timetable)) {
                if ($timetable_count > 0) {
                    $this->setVar("CAMPUS_NAME", $campus_list[$campus_id]['name']);
                    $this->parse('timetable_campus_name_block', 'timetable_campus_name');
                }
                else {
                    $this->setVar('timetable_campus_name_block', '');
                }
                $this->setVar("DAYNUMBER", $this->dayNumber[$campus_id]);
                $current_period = $this->currentPeriod[$campus_id];
                /* Setup colour map */
                $subjectColourMap = array();
                $colourIndex = 0;

                //for each period set result
                foreach ($timetable as $period) {
                    if ($period['periodSequence'] == 1) {
                        $this->setVar("PERIOD_NAME", $period['periodName']);
                        $this->setVar("PERIOD_START", $period['periodStart']);
                        $this->setVar("PERIOD_END", $period['periodEnd']);
                        $periodCount++;

                        if ($period['description']) {

                            $this->setVar('PERIOD_DESC', $period['description']);
                            $this->setVar('PERIOD_URL', '/homepage/' . urlencode($period['code']));
                            $this->setVar('PERIOD_CODE', '(' . $period['code'] . ')');
                            $this->setVar('PERIOD_ROOM', $period['room']);
                            $this->setVar('PERIOD_URL', '/homepage/' . urlencode($period['code']));

                            if ($current_period['PeriodNumber'] == $period['periodNumber']) {
                                $this->setVar(
                                    array(
                                        'PERIOD_CLASS' => 'current',
                                        'PERIOD_TH_CLASS' => 'dayCurrent'
                                    )
                                );
                            } else {
                                $this->setVar(
                                    array(
                                        'PERIOD_CLASS' => 'normal',
                                        'PERIOD_TH_CLASS' => 'normal'
                                    )
                                );
                            }

                            /* Handle colour array */
                            if ($period['code'] && !array_key_exists($period['code'], $subjectColourMap)) {
                                $subjectColourMap[$period['code']] = $this->colourList[$colourIndex]->hex_colour;
                                ++$colourIndex;
                            }

                            $this->setVar('PERIOD_COLOUR', '#' . $subjectColourMap[$period['code']]);


                            $this->setVar('period_class_block', '');
                            $this->setVar('period_class_link_block', '');

                            if ($period['description']) {
                                $cat = $this->category->getByCode($period['code']);
                                if ($cat['id'] && $this->group->getPermission($cat['id']) >= READ_ACCESS) {
                                    $this->parse('period_class_link_block', 'period_class_link');
                                } else {
                                    $this->parse('period_class_block', 'period_class');
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


                        } else {
                            $this->setVar(
                                array(
                                    'PERIOD_COLOUR' => '',
                                    'PERIOD_DESC' => '',
                                    'PERIOD_CODE' => '',
                                    'PERIOD_ROOM' => '',
                                    'PERIOD_ABSENTEES' => '',
                                    'PERIOD_URL' => '#',
                                    'has_attendances_block' => '',
                                    'PERIOD_CLASS' => 'normal',
                                    'PERIOD_TH_CLASS' => 'normal',
                                )
                            );

                            $this->parse('period_class_block', 'period_class');
                            $this->setVar('period_class_link_block', '');

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

                $this->parse("campus_timetable_block", 'campus_timetable', true);
            }
        }
        return true;
    }

}
