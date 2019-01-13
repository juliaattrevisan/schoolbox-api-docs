<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Router\Router;
use Schoolbox\Campus;
use Schoolbox\Collection;
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

    /**
     * The current period for each campus. Null if there is no current period
     *
     * @var null[]|mixed[][] $currentPeriod
     */
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
                        $this->currentPeriod[$campusId] = $timetableHandler->getPeriodNumber($campus, 0, $this->getUser()) ?: null;
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
     * Treats empty $subjectCode '' as a valid index. Hence all subjects which lack a code will have the same colour.
     *
     * @param string $subjectCode
     * @return string
     */
    private function getSubjectColour(string $subjectCode): string
    {
        if (!array_key_exists($subjectCode, $this->subjectColourMap)) {
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
            // Is ok to ignore. $subjectCode is coming from SIS and may not be present in Schoolbox.
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
            // We're ignoring this as $subjectCode is being supplied from the SIS and it simply means this SIS class is not
            // present in Schoolbox
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

                /* Example given by BTAC = [
                        "target":      "teacher.rollcall",
                        "ttid":        "23",
                        "yeargroups":  "10",
                        "classes":     "1",
                        "tchcode":     "VPE",
                        "subjectcode": "1111",
                        "date":        "01/05/2018",
                        "periodcode":  "3",
                        "daycode":     "7"
                    ]
                 */
                $deepLinkParams = [
                    'target'      => 'teacher.rollcall',
                    'ttid'        => $timetableCode,
                    'yeargroups'  => $year,
                    'classes'     => $class,
                    'tchcode'     => $this->getUser()->getExternalId(),
                    'subjectcode' => $subject,
                    'date'        => $now->getUsingFormat('d/m/Y'),
                    'periodcode'  => $period['periodNumber'],
                    'daycode'     => $this->dayNumber[$campusId],
                ];

                try {
                    /** @var Router $router */
                    $router   = $this->getSitePage()->getDi()->get('router');
                    $deepLink = urlencode(http_build_query($deepLinkParams));
                    return "{$router->generate('tassportal.teacherKiosk')}?deep_link={$deepLink}";
                } catch (\Aura\Router\Exception\RouteNotFound|RuntimeException|\Aura\Di\Exception\ServiceNotFound $e) {
                    \trigger_error($e->getMessage(), E_USER_WARNING);
                    return '';
                }
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
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
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
     * @param null|array          $currentPeriod NULL if there is no current period for the campus at this time.
     * @param string[][][]        $period
     * @param Alaress_Date_Format $now
     * @return array
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function buildPeriodContext(array $campusList, int $campusId, ?array $currentPeriod, array $period, Alaress_Date_Format $now): array
    {
        // Always highlight the current cell, regardless of whether it contains any classes
         $isCurrent = $currentPeriod
            && $currentPeriod['PeriodNumber'] == $period['periodNumber']
            && $currentPeriod['PeriodNumberSeq'] == $period['periodSequence'];

        $periodContext = [
            'name'       => $period['periodName'],
            'start'      => $period['periodStart'],
            'end'        => $period['periodEnd'],
            'isCurrent'  => $isCurrent,
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
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function buildClassContext(array $campusList, int $campusId, array $period, Alaress_Date_Format $now, array $class): array
    {
        if ($class['description']) {
            $classDescription = $class['description'];
        } elseif (!empty($class['code'])) {
            $classDescription = $this->getSubjectFolderName($class['code']);
        } else {
            $classDescription = '';
        }

        $classContext = [
            'code'      => $class['code'],
            'room'      => $class['room'],
            'url'       => empty($class['code']) ? '' : $this->getClassLink($class['code']),
            'colour'    => $this->getSubjectColour((string)$class['code']),
            'desc'      => $classDescription,
            'cancelled' => (bool)$class['ClassCancelled'],
        ];

        // Optionally link to Attendance Records
        if ('' !== $class['code'] && $this->getConfigMapper()->get('external_attendance') && $this->getUser()->getRole()->isStaff()) {

            $classContext['attendance'] = [
                'link'    => $this->getAttendanceLink($class, $now, $period, $campusList, $campusId),
                'marked'  => (bool)$class['AttendanceMarked'],
            ];
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
        $timetableContexts = [];

        // Setup colour map - doing so here implies the same subject code across different timetables be same colour
        $this->subjectColourMap = [];
        $this->colourIndex      = 0;

        foreach ($this->timetable as $campusId => $timetable) {

            // Don't render if external connector didn't return expected format, or if campus timetable lacks classes
            if (!\is_array($timetable) || !$this->getClassCountForTimetable($timetable)) {
                continue;
            }

            $timetableContexts[] = $this->buildTimetableContext($campusList, $campusId, $timetable, $now);
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@timetable/Dashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'externalType'        => $this->getConfigMapper()->get('external_type'),
                'campusTimetables'    => $timetableContexts,
            ],
            false,
            false,
            false,
            false
        );
    }
}
