<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Preview\Factory as PreviewFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\Task\Exception\ConcurrentModificationException;
use Schoolbox\Learning\Assessment\Task\Exception\InvalidTaskResponseException;


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
        Participant\DataSource $participantDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $workTypeMapper, $access, $transactor, $bakery, $termMapper, $participantDataSource);
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
     */
    public function getStatus(IAssessable $assessment)
    {
        // Ensure completions are sorted most recent first
        foreach($assessment->getAllParticipants() as $participant){
            $participant->getAllResponses()->sortByCallback(function(Assessment\IResponse $responseA, Assessment\IResponse $responseB){
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
                "@learningAssessment/task/status.twig",
                $twigData, "Status of {$assessment->getTitle()}", 'submission'
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
     * @throws ServiceNotFound
     * @throws \DomainException
     * @throws \Aura\Web\Exception\InvalidStatusCode if value is invalid
     * @throws \InvalidArgumentException If we attempt to give a task feedback a null response
     * @throws \PDOException If unable to save task response to DB
     * @throws InsufficientHydrationException If lack of hydration
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $previewFragment = [];

        /* If there was a specified url, it could be to some external location
         * or even to a storage file on this server - use the PreviewFactory to
         * decide which one it is
         */
        if ($assessment->getUrl()) {
            $translator = new \Schoolbox\Cyberhound\Url\Translator(
                $this->getSession()->get('ip'),
                $this->getConfigLoader()->get('cyberhound_api_url'),
                $this->getConfigLoader()->get('cyberhound_api_shared_key')
            );

            $previewFragment = (new PreviewFactory($translator))->urlFragment($assessment->getUrl());

            if (($previewFragment['type'] == 'redirect' || $previewFragment['type'] == 'iframe')
                && !$assessment->getDescription()) {

                // Don't save a response if instructor is looking at student's summary page
                if ($assessment->isSelfAssessable() && (int)$student->getId() === (int)$this->getUser()->getId()) {
                    try {
                        $this->saveTaskResponse(TaskCompletion::STATUS_SELF_ASSESSED_COMPLETE, $assessment, $student);
                    } catch (ConcurrentModificationException $e) {
                        // noop. This is expected to happen from time to time. No need to log.
                    } catch (InvalidTaskResponseException $e) {
                        // Something else when wrong. Probably a bug. Need to know about this.
                        $this->captureException($e);
                    }
                }

                /* Just forward if we only have a URL, regardless of iframe safe or not.
                 */
                $this->getResponse()->redirect->temporaryRedirect($previewFragment['fragment']);
                return;
            }
            elseif ($previewFragment['type'] == 'image') {

                /* The specified url is actually an image
                 *
                 * This actually has the same url structure as any other regular file
                 * so we need to check for image-ness first
                 *
                 * In this case $previewFragment['fragment'] already contains the url
                 * and we don't need to do anything more
                 */

            }
            elseif ($previewFragment['type'] == 'inline') {

                /* Embedded content like youtube videos etc
                 */
                $previewFragment['fragment'] = $this->buildUnthemedHtmlOutput($previewFragment['twig']['template'], $previewFragment['twig']['data'])->getAsString();
            }
            elseif ($foundHash = preg_match('/hash=([a-zA-Z0-9]+)/', $assessment->getUrl(), $matches)) {

                /* Other regular files
                 *
                 * Download them as some kind of attachment
                 */
                $previewFragment['type'] = 'file';
                try {
                    $previewFragment['file'] = StorageMapper::getInstance()->findByHash($matches[1]);
                }
                catch (\Schoolbox\Mapper\Exception $e) {
                    $previewFragment = [];
                }
            }

        }

        if ($this->getRequest()->query->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/'.$assessment->getBaseContext()->getFolder()->getId();
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
                "@learningAssessment/task/summary/student.twig",
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
     * @var int                 $_POST ['status'] One of the TaskCompletion::STATUS_* constants
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode if value is invalid
     * @throws \InvalidArgumentException If we attempt to give a task feedback a null response
     * @throws \PDOException If unable to save task response to DB
     * @throws \Schoolbox\Application\Exception\Exception If synchronous request and something went wrong
     * @throws InsufficientHydrationException If unable to perform some logic because an object lacks hydration
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
        } catch (ConcurrentModificationException|InvalidTaskResponseException $e) {

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
     * @throws ConcurrentModificationException If participant already has the task completion status - and don't want to log.
     * @throws InvalidTaskResponseException If task response is not saveable - and we want to log.
     * @throws InsufficientHydrationException If task object has not been sufficiently hydrated
     * @todo This should all be wrapped up in an auth rule
     */
    protected function assertSaveability(TaskResponse $response)
    {
        $submittedFor = $response->getSubmittedOnBehalfOf();
        $participant  = $response->getAssessable()->getParticipantIfExtant($submittedFor);

        // 1. Is the current session user an instructor
        $classFolder  = $response->getAssessable()->getBaseContext()->getFolder();
        $isInstructor = $this->getAccess()->isInstructor($this->getUser(), $classFolder);

        // 2. Is session user creating for another user, and is not an instructor ?
        if(!$isInstructor && (int)$submittedFor->getId() !== $this->getUser()->getId()){
            throw new InvalidTaskResponseException("Only instructors can mark other users");
        }

        // 3. Not yet a participant? And not an instructor or not marking themself?
        if(!$participant && (!$isInstructor || (int)$submittedFor->getId() !== (int)$this->getUser()->getId())){
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
            if ($mostRecentResponse && !$mostRecentResponse->isSelfAssessed() && $mostRecentResponse->getMarkedComplete()) {
                throw new InvalidTaskResponseException("Learners may not change a task completion status if the instructor has marked it complete.");
            }
        }
    }


    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $zoneFactory = new Assessment\Attachzone\Factory();
        $data['acceptingResponses'] = false;
        $data['attachzone'] = $zoneFactory->buildForAssessment($assessment)->getTwigContext();

        return $data;
    }


    /**
     * Generates, saves, then returns a domain-layer Task Response object
     *
     * @param int         $completionStatus status to mark
     * @param IAssessable $assessment
     * @param User        $student
     * @return TaskResponse $taskResponse Created task response
     * @throws \InvalidArgumentException If we attempt to give a task feedback a null response
     * @throws \PDOException If unable to save task response to DB
     * @throws ConcurrentModificationException If participant already has the task completion status.
     * @throws InvalidTaskResponseException If proposed Task Response is invalid for some reasons
     * @throws InsufficientHydrationException If task object is insufficiently hydrated
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
