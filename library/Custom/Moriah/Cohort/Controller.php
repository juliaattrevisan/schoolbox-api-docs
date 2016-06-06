<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Web\Controller\Base;
use Custom\Moriah\Cohort\Course\Statistics as CourseStatistics;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\StudentReportCard\ContextSelector;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Controller for Moriah's cohort page.
 *
 * @package Custom\Moriah\Cohort
 */
class Controller extends Base
{
    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    /**
     * @var UnitInstanceMapper
     */
    protected $unitInstanceMapper;

    /**
     * @var Term\Mapper
     */
    protected $termMapper;

    /**
     * Constructor
     *
     * @param Folder\Mapper      $folderMapper
     * @param UnitInstanceMapper $unitInstanceMapper
     * @param Term\Mapper        $termMapper
     */
    public function __construct(
        Folder\Mapper $folderMapper,
        UnitInstanceMapper $unitInstanceMapper,
        Term\Mapper $termMapper
    ) {
        $this->folderMapper       = $folderMapper;
        $this->unitInstanceMapper = $unitInstanceMapper;
        $this->termMapper         = $termMapper;
    }

    /**
     * Action for displaying a list of classes for a student.
     *
     * @param User $student
     */
    public function classList(User $student)
    {
        if (!$student->getRole()->isStudent()) {
            $this->getResponse()->status->setCode(404);
            return;
        }
        $classes = $this->folderMapper->findForUser($student);
        // Sort by name
        $classes->sortByCallback(
            function($a, $b) {
                /** @var Folder\Model $a */
                /** @var Folder\Model $b */
                $result = strnatcasecmp($a->getNameWithCodes(), $b->getNameWithCodes());
                if (!$result) {
                    return strnatcasecmp($a->getId(), $b->getId());
                }
                return $result;
            }
        );
        // Load all courses associated with these classes
        $courses = $this->unitInstanceMapper->findAllCoursesUsedInFolders($classes->getColumnValues('id'));
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/classList.twig',
            'title'    => 'Class List for '.$student->formatName(),
            'data'     => [
                'student' => $student,
                'classes' => $classes,
                'courses' => $courses,
            ],
        ]);
    }

    /**
     * Action for redirecting to the report for a single class for the current term.
     *
     * @param User         $student
     * @param Folder\Model $class
     */
    public function classReport(User $student, Folder\Model $class)
    {
        $term = $this->termMapper->findForToday();
        $this->getResponse()->redirect->found(
            $this->getRouter()->generate(
                'customMoriahCohort.classReportForTerm',
                [
                    'studentId' => $student->getId(),
                    'classId'   => $class->getId(),
                    'termCode'  => $term->getCode(),
                ]
            )
        );
    }

    /**
     * Action for displaying a single class, with cohort reporting.
     *
     * @param User         $student
     * @param Folder\Model $class
     * @param Term\Model   $term
     */
    public function classReportForTerm(User $student, Folder\Model $class, Term\Model $term)
    {
        if (!$student->getRole()->isStudent()) {
            $this->getResponse()->status->setCode(404);
            return;
        }

        // Build course list
        $courseLists = $this->unitInstanceMapper->findAllCoursesUsedInFolders([$class->getId()]);
        if (!isset($courseLists[$class->getId()])) {
            $this->getResponse()->status->setCode(404);
        } else {

            // Get all units in this course
            /** @var Folder\Model[] $courseList */
            $courseList = $courseLists[$class->getId()];
            $results    = [];
            foreach ($courseList as $course) {
                // ONLY assessment tasks
                // ONLY items that are on ALL folders in course
                $courseStats = new CourseStatistics(
                    $course,
                    $term,
                    $student,
                    $this->getUser(),
                    [WorkType::TYPE_ASSESSMENT_TASK]
                );
                $courseId    = $course->getId();
                $allFolders  = $courseStats->getAllFolders();

                $results[$courseId] = [
                    'course'  => $course,
                    'units'   => [],
                    'folders' => $allFolders,
                ];

                // Get all the units for all of these too.
                foreach ($allFolders as $folder) {
                    // Get all units within this course for this folder.
                    $allUnits = $this->unitInstanceMapper->findForCategoryId($folder->getId(), $course->getId());
                    // Get any assessment tasks from these units.
                    foreach ($allUnits as $unit) {
                        $baseUnitId = $unit->getUnitCategory()->getId();
                        if (!isset($results[$courseId]['units'][$baseUnitId])) {
                            $results[$courseId]['units'][$baseUnitId] = [
                                'unit'     => $unit,
                                'titles'   => $courseStats->extractTitles($unit, true),
                                'allMarks' => $courseStats->getAllUsersMarks($unit, true),
                                'myMarks'  => $courseStats->extractUserMarks($student, $unit, true),
                                'folder'   => $unit->getUnitCategory(),
                            ];
                        }
                    }
                }
            }

            $contextSelectorHtml = $this->generateContextSelector($term);

            // ¯\_(ツ)_/¯
            $class->loadTeachers();

            // And get the classes which use them?
            $this->getResponse()->content->set([
                'format'   => 'html',
                'template' => '@customMoriahCohort/classReportForTerm.twig',
                'title'    => $class->getNameWithCodes() . ' Class Report for '.$student->formatName(),
                'data'     => [
                    'user'            => $this->getUser(),
                    'student'         => $student,
                    'class'           => $class,
                    'courses'         => $courseList,
                    'results'         => $results,
                    'contextSelector' => $contextSelectorHtml,
                ],
            ]);
        }
    }

    /**
     * Generate a context selector phplib fragment.
     *
     * @param  Term\Model $term
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidComponent
     */
    private function generateContextSelector(Term\Model $term)
    {
        $currentUrl = ltrim($this->getRequest()->url->get(PHP_URL_PATH), '/');
        $currentUrl = preg_replace('/\/term\/(.*)/', '', $currentUrl);
        $currentUrl .= '/term';
        $contextSelector = new ContextSelector($currentUrl);
        $contextSelector->setTerms($this->termMapper->findAll());
        return $contextSelector->renderAsString($this->getDi()->get('sitePage'), ['term' => $term]);
    }
}
