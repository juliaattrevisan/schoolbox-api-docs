<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Mggs\Personalised;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Exception\InvalidStatusCode;
use Custom\Mggs\Personalised\Event;
use Custom\Mggs\Personalised\Participant;
use Schoolbox\Collection;
use Schoolbox\External\SubjectMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Assessment\Term;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\ClassListFactory;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation;

/**
 * Controller for the personalised learning screen.
 */
class Controller extends Base
{
    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * @var Participant\Mapper
     */
    protected $participantMapper;

    /**
     * @var AssessmentTranslator
     */
    protected $assessmentTranslator;
    /**
     * @var ClassListFactory
     */
    private $classListFactory;


    /**
     * Constructor
     *
     * @param Folder\Mapper        $folderMapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param Participant\Mapper   $participantMapper
     * @param AssessmentTranslator $assessmentTranslator
     * @param ClassListFactory     $classListFactory
     */
    public function __construct(
        Folder\Mapper $folderMapper,
        AssessmentDataSource $assessmentDataSource,
        Participant\Mapper $participantMapper,
        AssessmentTranslator $assessmentTranslator,
        ClassListFactory $classListFactory
    ) {
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->participantMapper    = $participantMapper;
        $this->assessmentTranslator = $assessmentTranslator;
        $this->classListFactory = $classListFactory;
    }


    /**
     * Redirects to the personalised learning screen for the current user.
     *
     * @api GET /learning/personalised/
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getPersonalised()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningPersonalised.getPersonalisedForStudent',
                [
                    'studentId' => $this->getUser()->getId(),
                ]
            )
        );
    }

    /**
     * Displays personalised learning screen for the given user.
     *
     * @api   GET /learning/personalised/:studentId/
     * @param User $student
     *
     * @throws ServiceNotFound
     */
    public function getPersonalisedForStudent(User $student)
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningPersonalised/personalised.twig',
                [
                    'student' => $student,
                ],
                'Personalised Learning for '.$student->formatName(),
                'submission'
            )
        );
    }

    /**
     * Retrieves the resources to display in this student's calendar.
     *
     * TODO consider not AJAXing this
     *
     * @api   GET /learning/personalised/resources/:studentId{/:date}
     *
     * @param Term\Model $term
     * @param User       $student
     */
    public function getResourcesForStudent(User $student, Term\Model $term)
    {
        $resultSet = [];

        $now = Alaress_Date_Format::now();

        $student->setSubjectCollection((new SubjectMapper())->findAllForStudent($student));

        $classList = new ClassList(
            $student,
            $this->folderMapper,
            \Timetable_ColourMapper::getInstance(), // not used in this context
            $this->classListFactory
        );

        /** @var GenericCollection|Folder\Model[] $folders */
        $folders = $classList->getClassList($term, $now)['classList'];

        foreach ($folders as $folder) {

            // Ignore non-classes
            if (strlen($folder->getCodeString()) === 0) {
                continue;
            }

            // TODO singleton
            $unitMapper = Implementation\Unit\Mapper::getInstance();

            try {
                /** @var GenericCollection $ids */
                $ids = $unitMapper->getJoinMapper('course')->findByColumn('folder_id', $folder->getId())->getPropertyValues('id');

                $where = $unitMapper->getPredicateBuilder()
                    ->in('course_implementation_id', $ids)
                    ->notLessThan('due_date', $term->getStart()->getDatabase())
                ;

                if (!$term->getActualFinish()->isNull()) {
                    $where->notGreaterThan('open_date', $term->getActualFinish()->getDatabase());
                }

                $units = $unitMapper->findByPredicates($where);

            } catch (\UnderflowException $e) {
                // no units
                continue;
            }

            foreach ($units as $unit) {

                /** @var Project[] $projects */
                $projects = $this->assessmentDataSource->findAllByContext($unit)->filter(function (IAssessable $assessment) {
                    return $assessment instanceof Project;
                });

                $resultSet[] = $this->makeResource($folder, $unit, 0, 'Mastery Tasks');

                foreach ($projects as $project) {
                    $resultSet[] = $this->makeResource($folder, $unit, $project->getId(), $project->getTitle());
                }
            }

        }

        $this->getResponse()->content->set($this->buildJsonOutput($resultSet));
    }

    private function makeResource(Folder\Model $folder, Implementation\Unit\Model $unit, $projectId, $projectName)
    {
        return [
            'id'      => sprintf('%d-%d-%d', $folder->getId(), $unit->getId(), $projectId),
            'subject' => $folder->getNameWithCodes(),
            'unit'    => $unit->getTitle(),
            'mastery' => $projectName
        ];
    }

    /**
     * Gets the resource calendar for this student (at this time).
     *
     * @param User $student
     *
     * @throws MapperException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getCalendarForStudent(User $student)
    {
        // Build the list
        $eventFactory  = new Event\Factory($this->getRouter(), $this->getConfigLoader());
        $assessments   = $this->assessmentDataSource->findAllByStudentWithinWindow(
            $student,
            true,
            new Alaress_Date_Format($this->getRequest()->query->get('start'), Alaress_Date_Format::TIMESTAMP),
            new Alaress_Date_Format($this->getRequest()->query->get('end'), Alaress_Date_Format::TIMESTAMP)
        );

        // All participations, including for projects
        $assessmentIds = [];
        foreach ($assessments as $assessment) {
            $assessmentIds[] = $assessment->getId();
            if ($assessment instanceof Project) {
                foreach ($assessment->getAssessables() as $activity) {
                    $assessmentIds[] = $activity->getId();
                }
            }
        }
        $participation = $this->participantMapper->findByColumnList([
            'assessment_id' => $assessmentIds,
            'student_id'    => $student->getId(),
        ]);

        $resultSet = $eventFactory->buildFromAssessmentCollection($assessments, $student, $participation);

        // Filter out non-unique values
        $newResultSet = new Collection();
        foreach ($resultSet as $row) {
            if (!isset($newResultSet[$row['id']])) {
                $newResultSet[$row['id']] = $row;
            }
        }

        $this->getResponse()->content->set($this->buildJsonOutput($newResultSet->reindex()));
    }

    /**
     * Saves the participation for this assessment.
     *
     * POST vars:
     * * assessmentId:      The assessment to update for
     * * openDate, dueDate: The open/due date the student sets for themselves
     *                      Format: timestamps
     *
     * @param User $student
     *
     * @throws InvalidStatusCode
     * @throws MapperException
     */
    public function postUpdateParticipation(User $student)
    {
        $post = $this->getRequest()->post;
        try {
            $assessment = $this->assessmentDataSource->findByIdForReview($post->get('assessmentId'), $student);

            if (!$assessment->isParticipant($student)) {
                $this->buildJsonResponse('Student is not a participant', 403);
                return;
            }

            // Validate this assessment can even be moved.
            $assessmentOpenDate = $assessment->getOpenAt();
            if ($assessmentOpenDate->isNull()) {
                $this->buildJsonResponse('This assessment does not have an open date', 403);
                return;
            }

            $assessmentDueDate = $assessment->getDueAt();
            if ($assessmentDueDate->isNull()) {
                $this->buildJsonResponse('This assessment does not have a due date', 403);
                return;
            }

        } catch (MapperException $e) {
            $this->buildJsonResponse('Assessment not found', 404);
            return;
        }


        // Build and validate open and due date
        try {
            $openDate = new Alaress_Date_Format($post->get('openDate'), Alaress_Date_Format::DATABASE_FORMAT);

        } catch (\InvalidArgumentException $e) {
            $this->buildJsonResponse('Invalid format for open date - provide as timestamp', 400);
            return;
        }

        try {
            $dueDate = new Alaress_Date_Format($post->get('dueDate'), Alaress_Date_Format::DATABASE_FORMAT);

        } catch (\InvalidArgumentException $e) {
            $this->buildJsonResponse('Invalid format for due date - provide as timestamp', 400);
            return;
        }

        // We have a valid assessment and date objects.
        // Check the dates are valid for the assessment.
        if ($openDate->lt($assessmentOpenDate)) {
            $text = sprintf(
                'Open date cannot be before assessment open date: failed to assert %s < %s',
                $assessmentOpenDate->getDatabase(),
                $openDate->getDatabase()
            );
            $this->buildJsonResponse($text, 400);
            return;
        }

        if ($dueDate->gt($assessmentDueDate)) {
            $text = sprintf(
                'Due date cannot be after assessment due date: failed to assert %s > %s',
                $assessmentDueDate->getDatabase(),
                $dueDate->getDatabase()
            );
            $this->buildJsonResponse($text, 400);
            return;
        }

        // All is valid - save the participation
        $participant = $this->participantMapper->createModel()
            ->setAssessment($this->assessmentTranslator->translateToDataModel($assessment)->getAssessmentModel())
            ->setStudent($student)
            // Set open and due of participation to start/end of day,
            // as that's how an assessment's dates should be if imported
            ->setOpenDate($openDate->startOfDay())
            ->setDueDate($dueDate->endOfDay());
        $this->participantMapper->save($participant);

        $this->buildJsonResponse('ok');
    }

    /**
     * Helper for building a JSON response
     *
     * @param string $resultString
     * @param int    $code
     *
     * @throws InvalidStatusCode
     */
    protected function buildJsonResponse($resultString, $code = 200)
    {
        $this->getResponse()->status->setCode($code);
        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                'result' => $resultString,
            ])
        );
    }
}
