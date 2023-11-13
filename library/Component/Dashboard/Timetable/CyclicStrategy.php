<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Fulfils the Dashboard Timetable component's requirements
 * when using a live connector
 */
class Component_Dashboard_Timetable_CyclicStrategy implements Component_Dashboard_Timetable_ComponentInterface
{
    /**
     * @var array
     */
    private array $timetable = [];


    /**
     * @var array $dayName
     */
    private array $dayName = [];


    /**
     * @var array $dayNumber
     */
    private array $dayNumber = [];


    /**
     * The current period for each campus. Null if there is no current period
     *
     * @var array $currentPeriod
     */
    private array $currentPeriod = [];


    /**
     * @var External_AttendanceLink
     */
    private External_AttendanceLink $externalAttendanceLink;


    /**
     * A list of colours to use
     *
     * @var Collection|Timetable_Colour[] $colourList
     */
    protected Collection $colourList;


    /**
     * Point to next colour from the colour list to assign to a subject
     *
     * @var int
     */
    private int $colourIndex;


    /**
     * Indexed by subject.code
     *
     * @var string[]
     */
    private array $subjectColourMap;


    /**
     * Constructor.
     *
     * @param IConfigLoader                     $configLoader
     * @param External_Timetable                $externalTimetable
     * @param \Schoolbox\User                   $user
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param Access                            $access
     * @param Component_Instance                $instance
     */
    public function __construct(
        private IConfigLoader $configLoader,
        private External_Timetable $externalTimetable,
        private \Schoolbox\User $user,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private Access $access,
        private Component_Instance $instance
    ) {
    }


    /**
     * Fetches the data that we need to render to users.
     *
     * @return bool
     */
    public function execute(): bool
    {
        /* is the user is hiding the timetable, or we aren't connected to an external db then don't show the timetable
         */
        if ($this->user->getHideTimetable() && $this->configLoader->get('external_enable')) {
            return false;
        }

        $timetableHandler = $this->externalTimetable;
        $this->externalAttendanceLink = new External_AttendanceLink($this->configLoader);
        $this->externalAttendanceLink->init($this->configLoader, $this->user->getRole()->isStaff());

        // Get current day and period. Don't output a timetable if it has the same code as another that has already been added

        $checkedCampusList = [];

        // Cloning, to avoid changing session user.
        $campuses = clone $this->user->getCampusCollection();
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
                        $this->currentPeriod[$campusId] = $timetableHandler->getPeriodNumber($campus, 0, $this->user) ?: null;
                        $this->timetable[$campusId]     = $timetableHandler->getTimetableForDate(
                            $this->user->getExternalId(),
                            $this->user->getId(),
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
        foreach ($this->user->getCampusCollection() as $campus) {
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
     * @param array               $campusList
     * @param int                 $campusId
     * @param array               $timetable
     * @param Alaress_Date_Format $now
     * @return array
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
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
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
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
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildClassContext(int $campusId, array $period, Alaress_Date_Format $now, array $class): array
    {
        $classDescription = $class['description'] ?? '';
        $classLink = '';
        $folder = $this->folderMapper->findForCampusAndClass((string)$class['campus'], (string)$class['code']);
        if ($folder) {
            $classDescription = $classDescription ?: $folder->getName();
            if ($this->access->getFolderAccess($this->user, $folder->getId()) >= UserAccess::ACCESS_READ) {
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
        if ($this->externalAttendanceLink->isEnabled((string)$class['code'], !empty($class['AttendanceEnabled']), $now)) {
            $classContext['attendance'] = [
                'link'   => $this->externalAttendanceLink->getAttendanceLink(
                    (string)$class['code'],
                    (string)$class['campus'],
                    $class['FileType'] ?? null,
                    $class['AttendancePeriods'] ?? [],
                    $period['periodNumber'],
                    $now,
                    $this->user->getCampusCollection(),
                    $campusId,
                    $this->user->getExternalId(),
                    $this->dayNumber[$campusId]
                ),
                'marked' => (bool)($class['AttendanceMarked'] ?? false),
                'target' => $this->externalAttendanceLink->getAttendanceLinkTarget(),
            ];
        }
        return $classContext;
    }


    /**
     * Renders data on screen for users
     *
     * @param string $templateHandle
     * @return bool|array
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function renderTemplate(string $templateHandle): bool|array
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

        return [
            'template' => '@timetable/Dashboard/component.twig',
            'data' => [
                'componentInstanceId' => $this->instance->getId(),
                'externalType'        => $this->configLoader->get('external_type'),
                'campusTimetables'    => $timetableContexts,
            ]
        ];
    }
}
