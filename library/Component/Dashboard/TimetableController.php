<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

class Component_Dashboard_TimetableController extends Component_Dashboard_ControllerAbstract
{
    /** @var External_TimetableInterface $externalTimetable */
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

    /** @var External_AttendanceLink */
    private $externalAttendanceLink;

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

        if (!(!empty($_SESSION['timetable']) && $this->getConfigLoader()->get('external_enable'))) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;
        $this->externalAttendanceLink = new External_AttendanceLink($this->getConfigLoader());
        $this->externalAttendanceLink->init($this->getConfigLoader(), $this->getUser()->getRole()->isStaff());

        // Get current day and period. Don't output a timetable if it has the same code as another that has already been added

        $checkedCampusList = [];

        // Cloning, to avoid changing session user.
        $campuses = clone $this->getUser()->getCampusCollection();
        $campuses->sortBy('sequence', 'ASC')->indexBy('id');
        foreach ($campuses as $campus) {
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
                            $this->getUser()->getExternalId(),
                            $this->getUser()->getId(),
                            $campus,
                            Alaress_Date_Format::now()
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

        foreach ($timetable as $period) {
            // Filter out periods like 'Recess' or 'Lunch' to only show those with classes
            if (1 !== (int)$period['periodSequence']) {
                continue;
            }
            $timetableContext['periods'][] = $this->buildPeriodContext($campusId, $currentPeriod, $period, $now);
        }
        return $timetableContext;
    }


    /**
     * Build twig data for a single period in a timetable
     *
     * @param int                 $campusId
     * @param null|array          $currentPeriod NULL if there is no current period for the campus at this time.
     * @param string[][][]        $period
     * @param Alaress_Date_Format $now
     * @return array
     */
    private function buildPeriodContext(int $campusId, ?array $currentPeriod, array $period, Alaress_Date_Format $now): array
    {
        // Always highlight the current cell, regardless of whether it contains any classes
        $isCurrent = $currentPeriod
            && $currentPeriod['PeriodNumber'] == $period['periodNumber']
            && $currentPeriod['PeriodNumberSeq'] == $period['periodSequence'];

        $periodContext = [
            'name'      => $period['periodName'],
            'start'     => $period['periodStart'],
            'end'       => $period['periodEnd'],
            'isCurrent' => $isCurrent,
        ];

        if (is_array($period['class'])) {
            foreach ($period['class'] as $class) {
                $periodContext['classes'][] = $this->buildClassContext($campusId, $period, $now, $class);
            }
        }
        return $periodContext;
    }


    /**
     * Builds data for a single class suitable for sending off to twig
     *
     * @param int                 $campusId
     * @param array               $period
     * @param Alaress_Date_Format $now
     * @param array               $class
     * @return array
     */
    private function buildClassContext(int $campusId, array $period, Alaress_Date_Format $now, array $class): array
    {
        $classDescription = $class['description'] ?? '';
        $classLink = '';
        $folder = $this->getFolderMapper()->findForCampusAndClass($class['campus'], $class['code']);
        if ($folder) {
            $classDescription = $classDescription ?: $folder->getName();
            if ($this->getAccess()->getFolderAccess($this->getUser(), $folder->getId()) >= UserAccess::ACCESS_READ) {
                // Yes you really do need to double url-encode this
                $classLink = '/homepage/code/' . urlencode(urlencode($folder->getMatchedCode()));
            }
        }

        $classContext = [
            'code'      => $class['code'],
            'room'      => $class['room'],
            'url'       => $classLink,
            'colour'    => $this->getSubjectColour((string)$class['code']),
            'desc'      => $classDescription,
            'cancelled' => !empty($class['ClassCancelled']),
        ];

        // Optionally link to Attendance Records
        if ($this->externalAttendanceLink->isEnabled($class['code'], !empty($class['AttendanceEnabled']), $now)) {
            $classContext['attendance'] = [
                'link'   => $this->externalAttendanceLink->getAttendanceLink(
                    $class['code'],
                    $class['campus'],
                    $class['FileType'] ?? null,
                    $class['AttendancePeriods'] ?? [],
                    $period['periodNumber'],
                    $now,
                    $this->getUser()->getCampusCollection(),
                    $campusId,
                    $this->getUser()->getExternalId(),
                    $this->dayNumber[$campusId]
                ),
                'marked' => (bool)($class['AttendanceMarked'] ?? false),
                'target' => $this->externalAttendanceLink->getAttendanceLinkTarget(),
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

            $timetableContext = $this->buildTimetableContext($campusList, $campusId, $timetable, $now);
            if (empty($timetableContext['periods'])) {
                continue;
            }
            $timetableContexts[] = $timetableContext;
        }

        if (empty($timetableContexts)) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@timetable/Dashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'externalType'        => $this->getConfigLoader()->get('external_type'),
                'campusTimetables'    => $timetableContexts,
            ],
            false,
            false,
            false,
            false
        );
    }
}
