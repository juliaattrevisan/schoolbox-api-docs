<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Exception\InvalidStatusCode;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\Cyberhound\Url\Translator;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Preview\Factory as PreviewFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\Task\Exception\ConcurrentModificationException;
use Schoolbox\Learning\Assessment\Task\Exception\InvalidTaskResponseException;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning\Assessment\Task
 */
class Controller extends Assessment\Controller
{

    /**
     * @var TaskResponseDataSource
     */
    protected $taskResponseDataSource;


    /**
     * Constructor
     *
     * @param UserMapper                    $userMapper
     * @param Error\Mapper                  $errorMapper
     * @param MapperProxy                   $assessmentMapper
     * @param WorkTypeMapper                $workTypeMapper
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param TaskResponseDataSource        $taskResponseDataSource
     * @param Term\Mapper                   $termMapper
     * @param Participant\DataSource        $participantDataSource
     * @param CourseSpecificationMapper     $courseSpecificationMapper
     * @param UnitSpecificationMapper       $unitSpecificationMapper
     * @param Replicator                    $replicator
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        TaskResponseDataSource $taskResponseDataSource,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator
    ) {
        parent::__construct(
            $userMapper,
            $errorMapper,
            $assessmentMapper,
            $workTypeMapper,
            $access,
            $transactor,
            $bakery,
            $termMapper,
            $participantDataSource,
            $courseSpecificationMapper,
            $unitSpecificationMapper,
            $replicator
        );
        $this->taskResponseDataSource = $taskResponseDataSource;
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     * @throws MapperException
     * @throws ServiceNotFound
     */
    public function getStatus(IAssessable $assessment): void
    {
        // Ensure completions are sorted most recent first
        foreach ($assessment->getAllParticipants() as $participant) {
            $participant->getAllResponses()->sortByCallback(static function (Assessment\IResponse $responseA, Assessment\IResponse $responseB) {
                return $responseB->getSubmittedAt()->compare($responseA->getSubmittedAt());
            });
        }

        $twigData = [
            'type'        => Assessment\AssessmentType::objectToString($assessment),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'  => $assessment,
            'writable'    => Access::getInstance()->isInstructor($this->getUser(), $assessment->getContext()->getFolder()),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/task/status.twig',
                $twigData,
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }


    /**
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     * @param IAssessable|Task $assessment
     * @param User             $student
     * @throws MapperException
     * @throws ServiceNotFound
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        /* Check whether SEB was required
         */
        if (!(new Assessment\ValidSebCheck())->isSatisfiedBy(
            'https://' . $this->getRequest()->server->get('HTTP_HOST') . $this->getRequest()->server->get('REQUEST_URI'),
            $assessment->getSebEnabled(),
            $this->getRequest()->server->getArrayCopy(),
            'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
            'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f'
        )) {
            die('seb check failed, download the stuff');
        }

        $previewFragment = [];

        /* If there was a specified url, it could be to some external location
         * or even to a storage file on this server - use the PreviewFactory to
         * decide which one it is
         */
        if ($assessment->getUrl()) {
            $translator = new Translator(
                $this->getSession()->get('ip'),
                $this->getConfigLoader()->get('cyberhound_api_url'),
                $this->getConfigLoader()->get('cyberhound_api_shared_key')
            );

            $previewFragment = (new PreviewFactory($translator))->urlFragment($assessment->getUrl());

            /* Embedded content like youtube videos etc as an inline hunk of html
             * It manifests under the file list
             */
            if ($previewFragment['type'] === 'inline') {
                $previewFragment['fragment'] = $this->buildUnthemedHtmlOutput($previewFragment['twig']['template'], $previewFragment['twig']['data'])->getAsString();
            }

            /* Otherwise, just go to the Summary screen and show all these pieces:
             * previewFragment['type'] is one of ['redirect', 'iframe', 'image']
             */
        }

        if ($this->getRequest()->query->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        // How should the jewelry transition?
        $classFolder          = $assessment->getBaseContext()->getFolder();
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $classFolder);
        $tickTransitions      = [
            'incomplete'      => $isViewerAnInstructor ? 'teacherAssessed' : 'selfAssessed',
            'selfAssessed'    => $isViewerAnInstructor ? 'teacherAssessed' : 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];

        $twigData = [
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'   => $assessment,
            'student'      => $student,
            'preview'      => $previewFragment,
            'returnUrl'    => $returnUrl,
            'transitions'  => $tickTransitions,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/task/summary/student.twig',
                $twigData
            )
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws NotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // this page does not exist
        throw new NotFound();
    }


    /**
     * Create a Task Response with specified completion status.
     *
     * If the most recent task response has the same status, this call will rejected.
     *
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws InvalidStatusCode
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // Did user mark (regardless of who they are) the task as incomplete ?
        $completionStatus = $this->getRequest()->post->getInt('status');

        try {
            $response = $this->saveTaskResponse($completionStatus, $assessment, $student);
            $this->getResponse()->status->setCode(200);

            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'message' => 'OK',
                    'id'      => $response->getId(),
                    'status'  => $completionStatus,
                ]));
            } else {
                // redirect after saving to Task Summary for particular Student
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate(
                        'learningAssessment.getSummary',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId'    => $student->getId(),
                        ]
                    )
                );
            }
        } catch (ConcurrentModificationException | InvalidTaskResponseException $e) {
            // We don't need to log concurrent exceptions, we expect them to happen from time to time.
            if (!$e instanceof ConcurrentModificationException) {
                $this->captureException($e);
            }

            $this->getResponse()->status->setCode(400);

            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'id'      => null,
                ]));
            } else {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_INVALID_TASK_COMPLETION, $e, $e->getMessage());
            }
        }
    }

    /**
     * Throws an exception if the proposed task response is not valid ?
     *
     * 1. Cannot interact if user has been excluded
     * 2. Only instructors can mark other users
     * 3. Responses cannot be created for users not participating in the task, unless it is an instructor marking themself
     * 4. Cannot create a new response with the same status as the most recent response for the student.
     * 5. Learners may not create a response if the task is not self-assessable
     * 6. Learners users may not create a new response if an instructor has marked them as complete
     *
     * @param TaskResponse $response
     * @throws MapperException
     */
    protected function assertSaveability(TaskResponse $response): void
    {
        $submittedFor = $response->getSubmittedOnBehalfOf();
        $participant  = $response->getAssessable()->getParticipantIfExtant($submittedFor);

        // 1. Is the current session user an instructor
        $classFolder  = $response->getAssessable()->getBaseContext()->getFolder();
        $isInstructor = $this->getAccess()->isInstructor($this->getUser(), $classFolder);

        // 2. Is session user creating for another user, and is not an instructor ?
        if (!$isInstructor && (int)$submittedFor->getId() !== $this->getUser()->getId()) {
            throw new InvalidTaskResponseException("Only instructors can mark other users");
        }

        // 3. Not yet a participant? And not an instructor or not marking themself?
        if (!$participant && (!$isInstructor || (int)$submittedFor->getId() !== (int)$this->getUser()->getId())) {
            throw new InvalidTaskResponseException("Only an non-excluded instructor marking themself is permitted to preview a task");
        }

        // 4. Does the most recent response for the student already have the proposed status?
        if (empty($response->getId())) {
            $mostRecentResponse = $participant ? $participant->findMostRecentResponse() : null;
            if ($mostRecentResponse instanceof TaskResponse && $mostRecentResponse->getCompletionStatus() === $response->getCompletionStatus()) {
                throw new ConcurrentModificationException("{$submittedFor->getFullName()} already has status ({$mostRecentResponse->getCompletionStatus()}) for this task");
            }
        }

        // Is the learner self-assessing ?
        if (!$isInstructor && $response->isSelfAssessed()) {
            // 5. Is the task not self-assessable ?
            if (!$response->getAssessable()->isSelfAssessable()) {
                throw new InvalidTaskResponseException("Learners may not self-assess a task if the task is not self-assessable");
            }

            // 6. Is the task already marked by the teacher as complete ?
            if (isset($mostRecentResponse) && !$mostRecentResponse->isSelfAssessed() && $mostRecentResponse->getMarkedComplete()) {
                throw new InvalidTaskResponseException("Learners may not change a task completion status if the instructor has marked it complete.");
            }
        }
    }


    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        $data = [
            'acceptingResponses' => false,
            'attachzone'         => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
        ];

        return $data;
    }


    /**
     * Generates, saves, then returns a domain-layer Task Response object
     *
     * @param int         $completionStatus status to mark
     * @param IAssessable $assessment
     * @param User        $student
     * @return TaskResponse
     * @throws MapperException
     */
    private function saveTaskResponse(int $completionStatus, IAssessable $assessment, User $student): Response\Model
    {
        $isMarkedComplete = $completionStatus !== TaskCompletion::STATUS_INCOMPLETE;

        $taskResponse = (new TaskResponse())
            ->setAssessable($assessment)
            ->setSubmittedOnBehalfOf($student)
            ->setSubmittedBy($this->getUser())
            ->setMarkedComplete($isMarkedComplete);

        if (TaskCompletion::STATUS_TEACHER_ASSESSED_COMPLETE === $completionStatus) {
            $feedback = (new Feedback\Model())
                ->setResponse($taskResponse);
            $taskResponse->getAllFeedback()->add($feedback);
        }

        $this->assertSaveability($taskResponse);

        // And save (no need to save a feedback - it will be built automatically from this response on DB retrieval)
        $this->taskResponseDataSource->save($taskResponse);

        return $taskResponse;
    }
}
