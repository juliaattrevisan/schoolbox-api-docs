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
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentTranslator;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

/**
 * Controller for the personalised learning screen.
 */
class Controller extends Base
{
    /**
     * @var FolderMapper
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
     * Constructor
     *
     * @param FolderMapper         $folderMapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param Participant\Mapper   $participantMapper
     * @param AssessmentTranslator $assessmentTranslator
     */
    public function __construct(
        FolderMapper $folderMapper,
        AssessmentDataSource $assessmentDataSource,
        Participant\Mapper $participantMapper,
        AssessmentTranslator $assessmentTranslator
    ) {
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->participantMapper    = $participantMapper;
        $this->assessmentTranslator = $assessmentTranslator;
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
     * @api   GET /learning/personalised/resources/:studentId/
     * @param User $student
     */
    public function getResourcesForStudent(User $student)
    {
        $resultSet = [];

        foreach ($this->folderMapper->findForUser($student) as $folder) {

            // Ignore non-classes
            if (strlen($folder->getCodeString()) === 0) {
                continue;
            }

            // Resource for folder
            $resultSet[] = [
                'id'          => $folder->getId(),
                'title'       => $folder->getTitle(),
                'folderTitle' => $folder->getTitle(),
            ];

            $baseResult = [
                'parentId'    => $folder->getId(),
                'folderId'    => $folder->getId(),
                'folderTitle' => $folder->getTitle(),
            ];

            // Resources for: units, content areas, projects, activities
            $resultSet[] = [
                'id'    => $folder->getId().'-unit',
                'title' => 'Units',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-contentArea',
                'title' => 'Content Areas',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-project',
                'title' => 'Projects',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-activity',
                'title' => 'Activities',
            ] + $baseResult;
        }

        $this->getResponse()->content->set($this->buildJsonOutput($resultSet));
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
        $eventFactory  = new Event\Factory($this->getRouter());
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
            $this->buildJsonResponse('Open date cannot be moved outside of assessment\'s open date', 400);
            return;
        }

        if ($dueDate->gt($assessmentDueDate)) {
            $this->buildJsonResponse('Due date cannot be moved outside of assessment\'s due date', 400);
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
