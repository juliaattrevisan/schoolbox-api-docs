<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Mggs\Learning\Upcoming;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Custom\Mggs\Learning\Personalised;

/**
 * Controller for the MGGS custom upcoming due work page.
 *
 * @deprecated As of SA-10363 this is no longer being used
 */
class Controller extends \Schoolbox\Learning\Upcoming\Controller
{

    /**
     * Displays upcoming work for the given user.
     *
     * If(request is for JSON)                       TODO (for a calendar scheduler?) If so, can we move this to a new route?
     *   returns JSON
     * elseif (user is in specific set of roles)
     *   returns custom HTML
     * else
     *   return core HTML
     *
     * @api   GET /learning/due/:studentId{/:date}
     * @param User                $student
     * @param Alaress_Date_Format $date The date to show on the work calendar.
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    public function getUpcomingForStudent(User $student, Alaress_Date_Format $date): void
    {
        // A little different for JSON requests
        if (\strpos($this->getRequest()->headers->get('accept'), 'application/json') !== false) {
            $this->getUpcomingForStudentJson($student, $date);
        } elseif (\in_array($student->getRole()->getId(), \explode(',', $this->getConfigLoader()->get(Personalised\Admin\Controller::CONFIG)), false)) {
            // Custom Page
            $this->getUpcomingForStudentCustom($student, $date);
        } else {
            // Core Page
            parent::getUpcomingForStudent($student, $date);
        }
    }


    /**
     * @api GET /learning/due/terms/:date
     *
     * @param Alaress_Date_Format $now
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getTermDates(Alaress_Date_Format $now): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput(array_map(function (Alaress_Date_Format $d) {
            return $d->getDatabaseDate();
        }, $this->findTermDates($now))));
    }


    /**
     * Returns a JSON payload of dueWork
     *
     * @param User                $student
     * @param Alaress_Date_Format $date
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function getUpcomingForStudentJson(User $student, Alaress_Date_Format $date): void
    {
        $term = $this->termMapper->findForDate($date);

        // Student's work
        $allWork = $this->assessmentDataSource
            ->findAllByStudent($student, true, $term)
            ->sortBy('dueAt', 'asc');

        $data = $allWork->map(function (IAssessable $assessable) {
            return [
                'id'          => $assessable->getId(),
                'title'       => $assessable->getTitle(),
                'folderId'    => $assessable->getBaseContext()->getFolder()->getId(),
                'folderTitle' => $assessable->getBaseContext()->getFolder()->getTitle(),
            ];
        });

        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Renders the custom DueWork HTML page
     *
     * @param User                $student
     * @param Alaress_Date_Format $date
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    private function getUpcomingForStudentCustom(User $student, Alaress_Date_Format $date): void
    {
        $term = $this->termMapper->findForDate($date);

        // Student's subjects and teachers
        $subjects = [];

        // Student's work
        $allWork = $this->assessmentDataSource
            ->findAllByStudent($student, true, $term)
            ->sortByCallback(new DueDateSortStrategy($student));

        // Only get subjects from external if student's timetable isn't hidden.
        // I don't get this logic as class list != timetable, but apparently
        // it's important. ¯\_(ツ)_/¯
        foreach ($this->folderMapper->findForUser($student, !$student->getHideTimetable(), UserAccess::ACCESS_READ, false, true) as $folder) {
            // Load teachers for this subject (if this subject has them)
            $teachers = [];
            if (\count($folder->getCodes()) > 0) {
                // Actual teachers only
                $teacherCodes = $this->externalClass->getTeachers($folder->getCodeString());
                if (\is_array($teacherCodes) && \count($teacherCodes) > 1) {
                    $teachers = $this->userMapper->findByColumn('synergy_id', $teacherCodes)
                        ->filter(
                            function (User $teacher) {
                                return $teacher->getRole()->isStaff();
                            }
                        );
                }
            }

            // Student's grades
            // I can just get the assessments out of $allWork
            $assessments = $allWork->filter(function (IAssessable $assessable) use ($folder) {
                return (int)$assessable->getBaseContext()->getFolder()->getId() === (int)$folder->getId();
            });

            // Markbook
            $markSchemeFactory = new Factory($this->getConfigLoader());
            $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());
            $markbook = new Markbook($assessments, $overallMarkScheme, $this->getUser());

            if (!isset($subjects[$folder->getId()])) {
                $subjects[$folder->getId()] = [
                    'teachers' => $teachers,
                    'folder'   => $folder,
                    'grade'    => $this->getConfigLoader()->get('show_overall_grade') ? $markbook->getStudentStatistics()[$student->getId()] : '',
                    'hasPerms'    => Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId()) > UserAccess::ACCESS_DENY,
                ];
            }
        }

        $visibility = new Visibility(Access::getInstance());

        // Only show work that student has permission to view, and can accept their submission
        $upcomingWork = $allWork->filter(function (IAssessable $assessable) use ($student, $visibility) {
            return $visibility->isVisibleToUser($assessable, $student, false);
        });

        // TODO Populate $_SESSION[children] properly
        $children = $this->userMapper->findByColumn('id', array_column($this->getSession()->get('children', []), 'id'));

        $returnUrl = Redirect::queryString($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', ['id' => $student->getId()]));

        $payload = [
            'student'      => $student,
            'date'         => $date,
            'subjects'     => $subjects,
            'mailHandle'   => $this->getSession()->get('mailhandle'),
            'currentTerm'  => $term,
            'children'     => $children,
            'upcomingWork' => $upcomingWork->limit(self::UPCOMING_WORK_LIMIT),
            'returnUrl'    => $returnUrl
        ];

        $termDates = $this->findTermDates($date);

        $payload['termDates'] = array_map(function (Alaress_Date_Format $d) {
            return $d->getDatabaseDate();
        }, $termDates);

        if ($termDates[0] && $termDates[3]) {
            $payload['termWeekCount'] = $termDates[0]->diffInWeeks($termDates[3]);
        }

        $namespace = Module::BASENAME . 'Mggs';
        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@$namespace/upcoming.twig", $payload)
                 ->setTitle('Upcoming Work for ' . $student->formatName())
                 ->setTab('submission')
        );
    }


    /**
     * @param Alaress_Date_Format $now
     * @return Alaress_Date_Format[]|array
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function findTermDates(Alaress_Date_Format $now): array
    {
        /** @var IConnector $edb */
        $edb = $this->getDi()->get('databaseExternal');

        $stmt = $edb->prepareQuery("
          SELECT TOP 1000 [FileYear]
              ,[FileSemester]
              ,[StartDate]
              ,[EndDate]
          FROM [dbo].[FileSemesters]
          WHERE FileYear = :fileYear
          ORDER BY FileSemestersSeq ASC
        ");

        $stmt->execute([ 'fileYear' => $now->getYear() ]);

        $terms = $semesters = [];

        // index by semester
        while ($row = $stmt->fetch()) {
            $fs = $row['FileSemester'];

            $payload = [
                'start' => Alaress_Date_Format::newDatabaseDate($row['StartDate']),
                'end'   => Alaress_Date_Format::newDatabaseDate($row['EndDate']),
            ];

            if ($fs <= 2) {
                $semesters[$fs] = $payload;
            } else {
                $terms[$fs] = $payload;
            }
        }

        return $this->locateDates($now, $semesters, $terms);
    }


    /**
     * @param Alaress_Date_Format $now
     * @param array               $semesters
     * @param array               $terms
     * @return Alaress_Date_Format[]
     */
    private function locateDates(Alaress_Date_Format $now, array $semesters, array $terms): array
    {
        $selectedSemester = null;

        foreach (array_reverse($semesters) as $semester) {
            if ($now < $semester['end']) {
                $selectedSemester = $semester;
            }
        }

        $termDates = [];

        foreach ($terms as $term) {
            if ($term['start'] >= $selectedSemester['start'] && $term['end'] <= $selectedSemester['end']) {
                $termDates[] = $term['start'];
                $termDates[] = $term['end'];
            }
        }

        return $termDates;
    }
}
