<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

class Component_Dashboard_TimetableController extends Component_Dashboard_ControllerAbstract
{
    /** @var External_Timetable $externalTimetable */
    private $externalTimetable;

    /** @var array $dayName */
    protected $dayName = [];

    /** @var array $dayNumber */
    protected $dayNumber = [];

    /** @var array $currentPeriod */
    private $currentPeriod = [];

    /** @var array $timetable */
    protected $timetable = [];

    /** @var Collection $colourList */
    protected $colourList;

    /** @var ConfigMapper $configMapper */
    protected $configMapper;
    
    /**
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     * @param External_Timetable $externalTimetable
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, External_Timetable $externalTimetable)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
        $this->configMapper = ConfigMapper::getInstance();
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if ($this->getUser()->getHideTimetable()) {
            return false;
        }

        if (!($_SESSION['timetable'] && $this->configMapper->get('external_enable'))) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;

        // Get current day and period. Don't output a timetable if it has the same code as another that has already been added

        $checkedCampusList = [];

        foreach ($this->getUser()->getCampusCollection() as $campus) {
            if ($campus instanceof Campus) {

                $campusId = $campus->getId();
                $campusCode = $campus->getExtTimetableCode();

                if (!isset($checkedCampusList[$campusCode])) {

                    $checkedCampusList[$campusCode] = true;
                    $dayNumber = $timetableHandler->getDayNumber($campus);

                    if ($dayNumber) {
                        $this->dayNumber[$campusId] = $dayNumber;
                        $this->dayName[$campusId] = $timetableHandler->getDayName($dayNumber);
                        $this->currentPeriod[$campusId] = $timetableHandler->getPeriodNumber($campus, 0, $this->getUser());
                        $this->timetable[$campusId] = $timetableHandler->getTimetableForDate(
                            $this->getUser()->getExternalId(), $this->getUser()->getId(), $campus, Alaress_Date_Format::now()
                        );
                    }
                }
            }
        }

        $this->colourList = Timetable_ColourMapper::getInstance()->findAll();

        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $periodCount = 0;
        $timetableCount = 0;

        if (!$this->loadTemplate($templateHandle, 'timetable')) {
            return false;
        }

        $this->setBlock($templateHandle, "campus_timetable", "campus_timetable_block");
        $this->setBlock("campus_timetable", "timetable_campus_name", "timetable_campus_name_block");
        $this->setBlock("campus_timetable", "timetable_period", "timetable_period_block");
        $this->setBlock("campus_timetable", "timetable_header", "timetable_header_block");
        $this->setBlock('timetable_period', 'period_class_link', 'period_class_link_block');
        $this->setBlock('timetable_period', 'period_class', 'period_class_block');
        $this->setBlock('period_class_link', 'period_class_link_attendance_synergetic', "period_class_link_attendance_synergetic_block");
        $this->setBlock('period_class_link', 'period_class_link_attendance_tass', "period_class_link_attendance_tass_block");
        $this->setBlock('period_class', 'period_class_attendance_synergetic', "period_class_attendance_synergetic_block");
        $this->setBlock('period_class', 'period_class_attendance_tass', "period_class_attendance_tass_block");


        foreach ($this->timetable as $timetable) {
            if (is_array($timetable) && !empty($timetable)) {
                $timetableCount++;
            }
        }

        if ($timetableCount === 0) {
            return false;
        }
        
        $campusList = [];
        
        foreach ($this->getUser()->getCampusCollection() as $campus) {
            $campusList[$campus->getId()] = $campus;
        }

        $this->setVar("campus_timetable_block", '');
        $now = new Alaress_Date_Format(time(), 'U');

        foreach ($this->timetable as $campusId => $timetable) {
            $classCount = 0;

            // Find out if the current campus timetable has any classes associated with the timetable definition. If not, skip its rendering altogether

            if (is_array($timetable) && count($timetable)) {
                foreach ($timetable as $currentPeriod) {
                    $classCount += count($currentPeriod['class']);
                }
            }

            if (is_array($timetable) && !empty($timetable) && $classCount) {
                
                if ($timetableCount > 1) {
                    $this->setVar("CAMPUS_NAME", $campusList[$campusId]->getName());
                    $this->parse('timetable_campus_name_block', 'timetable_campus_name');
                } else {
                    $this->setVar('timetable_campus_name_block', '');
                }
                
                $this->setVar("DAYNAME", $this->dayName[$campusId]);
                $currentPeriod = $this->currentPeriod[$campusId];
                
                // Setup colour map
                
                $subjectColourMap = [];
                $colourIndex = 0;

                // For each period set result
                
                $this->setVar('timetable_header_block', '');
                $this->setVar('timetable_period_block', '');

                foreach ($timetable as $period) {

                    if ($period['periodSequence'] == 1) {
                        
                        $this->setVar('period_class_block', '');
                        $this->setVar('period_class_link_block', '');
                        $this->setVar('period_class_attendance_synergetic_block', '');
                        $this->setVar('period_class_link_attendance_synergetic_block', '');
                        $this->setVar('period_class_attendance_tass_block', '');
                        $this->setVar('period_class_link_attendance_tass_block', '');
                        $this->setVar("PERIOD_NAME", $period['periodName']);
                        $this->setVar("PERIOD_START", $period['periodStart']);
                        $this->setVar("PERIOD_END", $period['periodEnd']);

                        $periodCount++;

                        // Always highlight the current cell, regardless of whether it has something on or not
                        
                        if ($currentPeriod['PeriodNumber'] == $period['periodNumber'] && $currentPeriod['PeriodNumberSeq'] == $period['periodSequence']) {
                            $this->setVar([
                                'PERIOD_CLASS' => 'timetable-subject-active',
                                'PERIOD_TH_CLASS' => 'timetable-period-active'
                            ]);
                        } else {
                            $this->setVar([
                                'PERIOD_CLASS' => '',
                                'PERIOD_TH_CLASS' => ''
                            ]);
                        }

                        // Display info only if it exists, otherwise empty cell
                        
                        if (count($period['class']) > 0) {
                            
                            foreach ($period['class'] as $class) {
                                $this->setVar('PERIOD_CODE', '(' . $class['code'] . ')');
                                $this->setVar('PERIOD_ROOM', $class['room']);
                                
                                // Yes you really do need to double url-encode this
                                
                                $this->setVar('PERIOD_URL', Scrub::text('/homepage/code/' . urlencode(urlencode($class['code']))));

                                // Handle colour array
                                
                                if ($class['code'] && !array_key_exists($class['code'], $subjectColourMap)) {
                                    if (!($this->colourList->offsetExists($colourIndex))) {
                                        $colourIndex = 0;
                                    }
                                    
                                    $subjectColourMap[$class['code']] = $this->colourList->offsetGet($colourIndex)->getHexColour();
                                    $colourIndex++;
                                }

                                $this->setVar('PERIOD_COLOUR', Scrub::text($subjectColourMap[$class['code']]));

                                // Set the class' description
                                $description = $class['description'];
                                try {
                                    $folder = $this->getFolderMapper()->findByCode($class['code']);
                                    $hasLink = $this->getAccess()->getFolderAccess($this->getUser(), $folder->getId()) >= UserAccess::ACCESS_READ;

                                    // If the class description is blank, fall back to the folder name

                                    if (empty($description)) {
                                        $description = $folder->getName();
                                    }
                                    
                                } catch (MapperException $e) {

                                    $hasLink = false;
                                }

                                // If both the class description and folder name as blank, fall back to "Unknown Class"

                                if (empty($description)) {
                                    $description = "Unknown Class";
                                }

                                $this->setVar('PERIOD_DESC', $description);

                                if($class['ClassCancelled']) {
                                    $this->setVar('CLASS_CANCELLED', 'strike');
                                }
                                else {
                                    $this->setVar('CLASS_CANCELLED','');
                                }

                                // Link to Attendance Records
                                
                                if (strlen($class['code']) && $this->configMapper->get('external_attendance') && $this->getUser()->getRole()->isStaff()) {

                                    if ($this->configMapper->get('external_type') == 'synergetic' && $class['AttendanceEnabled']) {

                                        $encodedClass = urlencode(urlencode(urlencode($class['code'])));
                                        $classLink = $class['campus'] ? $class['campus'] . '/' . $encodedClass : '0/' . $encodedClass;

                                        $this->setVar([
                                            'ATTENDANCE_CLASS_CODE' => $classLink,
                                            'ATTENDANCE_ATTENDANCE_DATE' => $now->getDatabaseDate(),
                                            'ATTENDANCE_ATTENDANCE_PERIOD' => $period['periodNumber'],
                                            'ATTENDANCE_TIMETABLE_CODE' => $campusList[$campusId]->getExtTimetableCode(),
                                            'ATTENDANCE_ICON' => $class['AttendanceMarked'] ? 'icon-approve' : 'icon-class-list',
                                        ]);

                                        if ($hasLink) {
                                            $this->parse('period_class_link_attendance_synergetic_block', 'period_class_link_attendance_synergetic');
                                        } else {
                                            $this->parse('period_class_attendance_synergetic_block', 'period_class_attendance_synergetic');
                                        }
                                        
                                    } elseif ($this->configMapper->get('external_type') == 'tass') {

                                        /** @noinspection PhpUnusedLocalVariableInspection */
                                        list($year, $subject, $class) = explode('-', $class['code']);

                                        /** @noinspection PhpUnusedLocalVariableInspection */
                                        list($cmpCode, $timetableCode) = explode('-', $campusList[$campusId]->getExtTimetableCode());
                                        
                                        $this->setVar([
                                            'ATTENDANCE_CLASS_CODE' => $class,
                                            'ATTENDANCE_SUBJECT_CODE' => $subject,
                                            'ATTENDANCE_ATTENDANCE_DATE' => $now->getUsingFormat('d/m/Y'),
                                            'ATTENDANCE_ATTENDANCE_DAY' => $this->dayNumber[$campusId],
                                            'ATTENDANCE_ATTENDANCE_PERIOD' => $period['periodNumber'],
                                            'ATTENDANCE_TIMETABLE_CODE' => $timetableCode,
                                            'ATTENDANCE_ICON' => 'icon-class-list',
                                        ]);

                                        if ($hasLink) {
                                            $this->parse('period_class_link_attendance_tass_block', 'period_class_link_attendance_tass');
                                        } else {
                                            $this->parse('period_class_attendance_tass_block', 'period_class_attendance_tass');
                                        }
                                    }
                                }

                                if ($hasLink) {
                                    $this->parse('period_class_link_block', 'period_class_link', true);
                                } else {
                                    $this->parse('period_class_block', 'period_class', true);
                                }

                                /* Clear children after parsing out the parent
                                 */
                                $this->setVar([
                                    'period_class_link_attendance_block' => '',
                                    'period_class_attendance_block' => ''
                                ]);
                            }
                        }

                        // Output period block
                        
                        $this->parse("timetable_header_block", "timetable_header", true);
                        $this->parse("timetable_period_block", "timetable_period", true);
                    }
                }

                // Timetable field width
                
                $this->setVar("TIMETABLE_FIELD_WIDTH", floor(((98 - ($periodCount * 0.05)) / $periodCount)));
                $this->parse("campus_timetable_block", 'campus_timetable', true);
            }
        }

        return true;
    }
}
