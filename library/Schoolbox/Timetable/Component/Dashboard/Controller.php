<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Timetable\Component\Dashboard;

use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use Component_Dashboard_ControllerAbstract;
use External_Timetable;
use Timetable_Colour;
use Component_Instance;
use SitePage;
use Alaress_Date_Format;
use Timetable_ColourMapper;
use Exception;

class Controller extends Component_Dashboard_ControllerAbstract
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

    /** @var Timetable_Colour[]|Collection $colourList */
    protected $colourList;

    /**
     * Indexed by subject.code
     *
     * @var string[]
     */
    private $subjectColourMap;

    /**
     * Point to next colour from the colour list to assign to a subject
     *
     * @var int
     */
    private $colourIndex;


    /**
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @param External_Timetable $externalTimetable
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, External_Timetable $externalTimetable)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->externalTimetable = $externalTimetable;
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

        if (!($_SESSION['timetable'] && $this->getConfigMapper()->get('external_enable'))) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;

        // Get current day and period. Don't output a timetable if it has the same code as another that has already been added

        $checkedCampusList = [];

        foreach ($this->getUser()->getCampusCollection() as $campus) {
            if ($campus instanceof Campus) {

                $campusId   = $campus->getId();
                $campusCode = $campus->getExtTimetableCode();

                if (!isset($checkedCampusList[$campusCode])) {

                    $checkedCampusList[$campusCode] = true;
                    $dayNumber                      = $timetableHandler->getDayNumber($campus);

                    if ($dayNumber) {
                        $this->dayNumber[$campusId]     = $dayNumber;
                        $this->dayName[$campusId]       = $timetableHandler->getDayName($dayNumber);
                        $this->currentPeriod[$campusId] = $timetableHandler->getPeriodNumber($campus, 0, $this->getUser());
                        $this->timetable[$campusId]     = $timetableHandler->getTimetableForDate(
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
     * Returns the campuses associated with current user
     *
     * @return Campus[]
     */
    private function getCampusList(): array
    {
        $campusList = [];
        foreach ($this->getUser()->getCampusCollection() as $campus) {
            $campusList[$campus->getId()] = $campus;
        }
        return $campusList;
    }


    /**
     * Returns the number of classes in the timetable
     *
     * @param array $timetable
     * @return int
     */
    private function getClassCountForTimetable(array $timetable): int
    {
        $classCount = 0;
        foreach ($timetable as $currentPeriod) {
            $classCount += \count($currentPeriod['class']);
        }
        return $classCount;
    }


    /**
     * Gets a background colour for a subject
     *
     * @param string $subjectCode
     * @return string
     */
    private function getSubjectColour(string $subjectCode): string
    {
        if ($subjectCode && !array_key_exists($subjectCode, $this->subjectColourMap)) {
            if (!$this->colourList->offsetExists($this->colourIndex)) {
                $this->colourIndex = 0; // Loop back to start of colour list
            }

            $this->subjectColourMap[$subjectCode] = $this->colourList[$this->colourIndex]->getHexColour();
            $this->colourIndex++;
        }
        return $this->subjectColourMap[$subjectCode];
    }


    /**
     * Returns the subject's folder's name, or empty string if not found
     *
     * @param string $subjectCode
     * @return string
     */
    private function getSubjectFolderName(string $subjectCode): string
    {
        try {
            return $this->getFolderMapper()->findByCode($subjectCode)->getName();
        } catch (MapperException $e) {
            return '';
        }
    }


    /**
     * Returns a link from a class to a homepage if user permitted to follow it
     *
     * @param string $subjectCode
     * @return string
     */
    private function getClassLink(string $subjectCode): string
    {
        try {
            $folder = $this->getFolderMapper()->findByCode($subjectCode);
            if ($this->getAccess()->getFolderAccess($this->getUser(), $folder->getId()) >= UserAccess::ACCESS_READ) {
                // Yes you really do need to double url-encode this
                return '/homepage/code/' . urlencode(urlencode($subjectCode));
            }
            return '';
        } catch (MapperException $e) {
            return '';
        }
    }


    /**
     * Returns a link the user can follow to mark attendance in their SIS
     *
     * @param array               $class
     * @param Alaress_Date_Format $now
     * @param array               $period
     * @param array               $campusList
     * @param int                 $campusId
     * @return string
     */
    private function getAttendanceLink(array $class, Alaress_Date_Format $now, array $period, array $campusList, int $campusId): string
    {
        switch ($this->getConfigMapper()->get('external_type')) {
            case 'synergetic':
                if (!$class['AttendanceEnabled']) {
                    return '';
                }
                $encodedClass = urlencode(urlencode(urlencode($class['code'])));
                $classLink    = $class['campus'] ? $class['campus'] . '/' . $encodedClass : '0/' . $encodedClass;
                return "/modules/attendance/modify/{$classLink}/{$now->getDatabaseDate()}/{$period['periodNumber']}/{$campusList[$campusId]->getExtTimetableCode()}";
            case 'tass':

                /** @noinspection PhpUnusedLocalVariableInspection */
                [$year, $subject, $class] = explode('-', $class['code']);
                [$cmpCode, $timetableCode] = explode('-', $campusList[$campusId]->getExtTimetableCode());

                $deepLinkParams = [
                    'target'      => 'teacher.rollcall',
                    'ttid'        => $timetableCode,
                    'yeargroups'  => '',
                    'classes'     => $class,
                    'tchcode'     => $this->getUser()->getExternalId(),
                    'subjectcode' => $subject,
                    'date'        => $now->getUsingFormat('d/m/Y'),
                    'periodcode'  => $period['periodNumber'],
                    'daycode'     => $this->dayNumber[$campusId],
                ];
                $deepLink       = urlencode(implode('&', $deepLinkParams));
                return "/modules/tassportal/teacherkiosk?deep_link={$deepLink}";
            default:
                return '';
        }
    }


    /**
     * TODO move this to twig macro, inject three flags [externalType, attendanceEnabled, attendanceMarked]
     *
     * @param array $class
     * @return string
     */
    private function getAttendanceIcon(array $class): string
    {
        switch ($this->getConfigMapper()->get('external_type')) {
            case 'synergetic':
                if (!$class['AttendanceEnabled']) {
                    return '';
                }
                return $class['AttendanceMarked'] ? 'icon-approve' : 'icon-class-list';
            case 'tass':
                return $class['AttendanceEnabled'] ? 'icon-class-list' : '';
            default:
                return '';
        }
    }


    /**
     * Builds twig context data for a single timetable
     *
     * @param Campus[]            $campusList
     * @param int                 $campusId
     * @param array               $timetable
     * @param Alaress_Date_Format $now
     * @return array
     */
    private function buildTimetableContext(array $campusList, int $campusId, array $timetable, Alaress_Date_Format $now): array
    {
        // Start building twig data
        $timetableContext = [
            'campus'  => $campusList[$campusId]->getName(),
            'day'     => $this->dayName[$campusId],
            'periods' => [],
        ];

        $currentPeriod = $this->currentPeriod[$campusId];

        // Setup colour map
        $this->subjectColourMap = [];
        $this->colourIndex      = 0;

        foreach ($timetable as $period) {
            // Filter out periods like 'Recess' or 'Lunch' to only show those with classes
            if (1 !== (int)$period['periodSequence']) {
                continue;
            }
            $timetableContext['periods'][] = $this->buildPeriodContext($campusList, $campusId, $currentPeriod, $period, $now);
        }
        return $timetableContext;
    }


    /**
     * Build twig data for a single period in a timetable
     *
     * @param array               $campusList
     * @param int                 $campusId
     * @param array               $currentPeriod
     * @param string[][][]        $period
     * @param Alaress_Date_Format $now
     * @return array
     */
    private function buildPeriodContext(array $campusList, int $campusId, array $currentPeriod, array $period, Alaress_Date_Format $now): array
    {
        // Always highlight the current cell, regardless of whether it has something on or not
        $thCssClass = ($currentPeriod['PeriodNumber'] == $period['periodNumber'] && $currentPeriod['PeriodNumberSeq'] == $period['periodSequence'])
            ? 'timetable-period-active'
            : '';
        $tdCssClass = ($currentPeriod['PeriodNumber'] == $period['periodNumber'] && $currentPeriod['PeriodNumberSeq'] == $period['periodSequence'])
            ? 'timetable-subject-active'
            : '';

        $periodContext = [
            'name'       => $period['periodName'],
            'start'      => $period['periodStart'],
            'end'        => $period['periodEnd'],
            'thCssClass' => $thCssClass,
            'tdCssClass' => $tdCssClass,
        ];

        foreach ($period['class'] as $class) {
            $periodContext['classes'][] = $this->buildClassContext($campusList, $campusId, $period, $now, $class);
        }
        return $periodContext;
    }


    /**
     * Builds data for a single class suitable for sending off to twig
     *
     * @param array               $campusList
     * @param int                 $campusId
     * @param array               $period
     * @param Alaress_Date_Format $now
     * @param array               $class
     * @return array
     */
    private function buildClassContext(array $campusList, int $campusId, array $period, Alaress_Date_Format $now, array $class): array
    {
        $classContext = [
            'code'      => "({$class['code']})",
            'room'      => $class['room'],
            'url'       => $this->getClassLink($class['code']),
            'colour'    => $this->getSubjectColour($class['code']),
            'desc'      => $class['description'] ?: $this->getSubjectFolderName($class['code']),
            'cancelled' => (bool)$class['ClassCancelled'],
        ];

        // Optionally link to Attendance Records
        if ('' !== $class['code'] && $this->getConfigMapper()->get('external_attendance') && $this->getUser()->getRole()->isStaff()) {

            $classContext = array_merge($classContext, [
                'attendanceLink' => $this->getAttendanceLink($class, $now, $period, $campusList, $campusId),
                'attendanceIcon' => $this->getAttendanceIcon($class),
            ]);
        }
        return $classContext;
    }


    /**
     * @param string $templateHandle
     * @return bool
     * @throws Exception
     */
    public function renderTemplate($templateHandle)
    {
        $campusList = $this->getCampusList();
        $now        = new Alaress_Date_Format(time(), 'U');
        $timetables = [];

        foreach ($this->timetable as $campusId => $timetable) {

            // Don't render if external connector didn't return expected format, or if campus timetable lacks classes
            if (!\is_array($timetable) || !$this->getClassCountForTimetable($timetable)) {
                continue;
            }

            $timetables[] = $this->buildTimetableContext($campusList, $campusId, $timetable, $now);
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@timetable/Dashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'campusTimetables'    => $timetables,
            ],
            false,
            false,
            false,
            false
        );
    }
}
