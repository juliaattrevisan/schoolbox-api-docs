<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 *
 */
class Component_Dashboard_TimetableController extends Component_Dashboard_ControllerAbstract
{
    private $externalTimetable;

    protected $dayName = array();

    protected $dayNumber = array();

    private $currentPeriod = array();

    protected $timetable = array();

    /**
     * @var Schoolbox\Collection
     */
    protected $colourList;

    /**
     * The html file to load
     *
     * @var string
     */
    protected $templateFile = 'timetable';

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, Group $group, Category $category, External_Timetable $externalTimetable)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
        $this->group = $group;
        $this->category = $category;
    }

    public function execute()
    {
        if (!($_SESSION['timetable'] && Cfg::get('external_enable'))) {
            return false;
        }


        $timetableHandler = $this->externalTimetable;

        // get current day and period.
        // Don't output a timetable if it has the same code as another that has
        // already been added.
        $checked_campus_list = array();
        foreach ($_SESSION['user']->getCampusCollection() as $campus) {
            if ($campus instanceof Schoolbox\Campus) {
                $campus_id = $campus->getId();
                $campus_code = $campus->getExtTimetableCode();
                if (!isset($checked_campus_list[$campus_code])) {
                    $checked_campus_list[$campus_code] = true;
                    $day_number = $timetableHandler->getDayNumber($campus);
                    if ($day_number) {
                        $this->dayNumber[$campus_id] = $day_number;
                        $this->dayName[$campus_id] = $timetableHandler->getDayName($day_number);
                        $this->currentPeriod[$campus_id] = $timetableHandler->getPeriodNumber($campus);
                        $this->timetable[$campus_id] = $timetableHandler->getTimetableForDate(
                            $_SESSION['sid'], $_SESSION['id'], $campus, Alaress_Date_Format::now()
                        );
                    }
                }
            }
        }

        $this->colourList = Timetable_ColourMapper::getInstance()->findAll();

        return true;


    }

    function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, $this->getTemplateFile())) {
            return false;
        }

        $this->setBlock($templateHandle, "campus_timetable", "campus_timetable_block");
        $this->setBlock("campus_timetable", "timetable_campus_name", "timetable_campus_name_block");
        $this->setBlock("campus_timetable", "timetable_period", "timetable_period_block");
        $this->setBlock("campus_timetable", "timetable_header", "timetable_header_block");
        $this->setBlock('timetable_period', 'period_class_link', 'period_class_link_block');
        $this->setBlock('timetable_period', 'period_class', 'period_class_block');
        $this->setBlock('period_class_link', 'period_class_link_attendance', "period_class_link_attendance_block");
        $this->setBlock('period_class', 'period_class_attendance', "period_class_attendance_block");

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
        foreach ($this->user->getCampusCollection() as $campus) {
            $campus_list[$campus->getId()] = $campus;
        }

        $this->setVar("campus_timetable_block", '');
        $now = new Alaress_Date_Format(time(), 'U');
        $attendance_date = $now->getDatabaseDate();

        foreach ($this->timetable as $campus_id => $timetable) {
            $classCount = 0;

            /* Find out if the current campus timetable has any classes associated
            * with the timetable definition. If not, skip its rendering altogether
            */
            if (is_array($timetable) && count($timetable)) {
                foreach ($timetable as $currentPeriod) {
                    $classCount += count($currentPeriod['class']);
                }
            }

            if (is_array($timetable) && !empty($timetable) && $classCount) {
                if ($timetable_count > 1) {
                    $this->setVar("CAMPUS_NAME", $campus_list[$campus_id]->getName());
                    $this->parse('timetable_campus_name_block', 'timetable_campus_name');
                } else {
                    $this->setVar('timetable_campus_name_block', '');
                }
                $this->setVar("DAYNAME", $this->dayName[$campus_id]);
                $current_period = $this->currentPeriod[$campus_id];
                /* Setup colour map */
                $subjectColourMap = array();
                $colourIndex = 0;

                //for each period set result
                $this->setVar('timetable_header_block', '');
                $this->setVar('timetable_period_block', '');

                foreach ($timetable as $period) {

                    if ($period['periodSequence'] == 1) {
                        $this->setVar('period_class_block', '');
                        $this->setVar('period_class_link_block', '');
                        $this->setVar('period_class_attendance_block', '');
                        $this->setVar('period_class_link_attendance_block', '');
                        $this->setVar("PERIOD_NAME", $period['periodName']);
                        $this->setVar("PERIOD_START", $period['periodStart']);
                        $this->setVar("PERIOD_END", $period['periodEnd']);
                        $periodCount++;

                        /* Always highlight the current cell, regardless of
                         * whether it has something on or not
                         */
                        if ($current_period['PeriodNumber'] == $period['periodNumber']) {
                            $this->setVar(
                                array(
                                    'PERIOD_CLASS'    => 'timetable-subject-active',
                                    'PERIOD_TH_CLASS' => 'timetable-period-active'
                                )
                            );
                        } else {
                            $this->setVar(
                                array(
                                    'PERIOD_CLASS'    => '',
                                    'PERIOD_TH_CLASS' => ''
                                )
                            );
                        }

                        /* Display info only if it exists
                         * Otherwise empty cell
                         */
                        if (count($period['class']) > 0) {
                            foreach($period['class'] as $class) {
                                $this->setVar('PERIOD_DESC', $class['description']);
                                $this->setVar('PERIOD_CODE', '(' . $class['code'] . ')');
                                $this->setVar('PERIOD_ROOM', $class['room']);
                                // Yes you really do need to double url-encode this
                                $this->setVar(
                                    'PERIOD_URL',
                                    Scrub::text(
                                        '/homepage/code/' . urlencode(urlencode($class['code']))
                                    )
                                );

                                /* Handle colour array */
                                if ($class['code'] && !array_key_exists($class['code'], $subjectColourMap)) {
                                    if (!($this->colourList->offsetExists($colourIndex))) {
                                        $colourIndex = 0;
                                    }
                                    $subjectColourMap[$class['code']] = $this->colourList->offsetGet($colourIndex)->getHexColour();
                                    ++$colourIndex;
                                }

                                $this->setVar('PERIOD_COLOUR', '#' . Scrub::text($subjectColourMap[$class['code']]));

                                $cat     = $this->category->getByCode($class['code']);
                                $hasLink = $cat['id'] && $this->group->getPermission($cat['id']) >= READ_ACCESS;

                                // Link to Attendance Records
                                if (strlen($class['code'])
                                    && Cfg::get('external_type') == 'synergetic'
                                    && Cfg::get('external_attendance')
                                    && $this->group->isStaff()
                                    && $class['AttendanceEnabled']
                                ) {
                                    $this->setVar(
                                        array(
                                            'ATTENDANCE_CLASS_CODE'        => $class['campus'] ? $class['campus'] . '/' . $class['code'] : '0/' . $class['code'],
                                            'ATTENDANCE_ATTENDANCE_DATE'   => $attendance_date,
                                            'ATTENDANCE_ATTENDANCE_PERIOD' => $period['periodNumber'],
                                            'ATTENDANCE_ICON'              => $class['AttendanceMarked'] ? 'icon-approve' : 'icon-class-list',
                                        )
                                    );

                                    if ($hasLink) {
                                        $this->parse('period_class_link_attendance_block', 'period_class_link_attendance');
                                    }
                                    else {
                                        $this->parse('period_class_attendance_block', 'period_class_attendance');
                                    }
                                }

                                if ($hasLink) {
                                    $this->parse('period_class_link_block', 'period_class_link',true);
                                }
                                else {
                                    $this->parse('period_class_block', 'period_class',true);
                                }
                            }
                        }

                        //output period block
                        $this->parse("timetable_header_block", "timetable_header", true);
                        $this->parse("timetable_period_block", "timetable_period", true);
                    }
                }

                //timetable field width
                $this->setVar("TIMETABLE_FIELD_WIDTH", floor(((98 - ($periodCount * 0.05)) / $periodCount)));

                $this->parse("campus_timetable_block", 'campus_timetable', true);
            }
        }
        return true;
    }

    /* Getters/Setters
     */

    /**
     * @param string $templateFile
     */
    protected function setTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * @return string
     */
    protected function getTemplateFile()
    {
        return $this->templateFile;
    }
}
