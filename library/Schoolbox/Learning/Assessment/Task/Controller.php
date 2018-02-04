<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @level 165
     * @param IAssessable|Task $assessment
     * @param User        $student
     * @throws ServiceNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);
        $previewFragment = [];

        /* If there was a specified url, it could be to some external location
         * or even to a storage file on this server - use the PreviewFactory to
         * decide which one it is
         */
        if ($assessment->getUrl()) {
            $previewFragment = (new PreviewFactory($this->getRouter()))->urlFragment($assessment->getUrl(), $this->getUser());

            if ($previewFragment['type'] == 'redirect' && !$assessment->getDescription()) {

                /* Just forward if we only have a URL
                 */
                $this->getResponse()->redirect->temporaryRedirect($assessment->getUrl());
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
                    unset($previewFragment);
                }
            }

        }

        if ($this->getRequest()->query->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/'.$assessment->getBaseContext()->getFolder()->getId();
        }

        $twigData = [
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'   => $assessment,
            'hasWritePerm' => $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder()),
            'preview'      => $previewFragment,
            'participant'  => $participant,
            'returnUrl'    => $returnUrl,
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
     * @level 165
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
     * @level 165
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \PDOException
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // Did user mark (regardless of who they are) the task as incomplete ?
        $isMarkedComplete = $this->getRequest()->post->getInt('status') != TaskCompletion::STATUS_INCOMPLETE;

        $response = (new TaskResponse())
            ->setAssessable($assessment)
            ->setSubmittedOnBehalfOf($student)
            ->setSubmittedBy($this->getUser())
            // TODO figure out whether to set create time as now, or set it as a created timestamp in mapper
            ->setCreatedAt(\Alaress_Date_Format::now())
            ->setMarkedComplete($isMarkedComplete);

        try{
            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                $this->assertSaveability($participant, $response);
            }
        } catch (\UnexpectedValueException $e){

            // Cast reason to standardised/localised representation
            $errorException = $this->getErrorMapper()->generateException(Error\Mapper::ERROR_INVALID_TASK_COMPLETION, null, $e->getMessage());

            $result['success']   = false;
            $result['message']   = $errorException->getMessage();
            $result['id']        = null;
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($result));
            return;
        }

        // And save (no need to save a feedback - it will be built automatically from this response on DB retrieval)
        $this->taskResponseDataSource->save($response);

        // And redirect somewhere
        if ($this->getRequest()->isXhr()) {
            $result['success'] = true;
            $result['message'] = 'OK';
            $result['id']      = $response->getId();
            $result['status']  = $response->getCompletionStatus();
            $this->getResponse()->status->setCode(200);
            $this->getResponse()->content->set($this->buildJsonOutput($result));

        } else {
            // redirect after saving to Due Work Summary For Particular Student
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
    }

    /**
     * Can current user save the proposed TaskResponse for the specified Participant ?
     *
     * * Instructors cannot mark a student if they lack WRITE+ perm to class
     * * Responses cannot be created for users not participating in the task.
     * * Cannot create a new response with the same status as the most recent response for the student.
     * * Participants may not create a response if the task is not self-assessable
     * * Participants may not create a new response if the teacher has marked them as complete
     *
     * @param Participant  $participant
     * @param TaskResponse $response
     * @throws \UnexpectedValueException if task response is not saveable
     */
    protected function assertSaveability(Participant $participant, TaskResponse $response)
    {
        $submittedBy  = $response->getSubmittedBy();
        $submittedFor = $response->getSubmittedOnBehalfOf();

        // Is teacher marking a student ?
        if($participant->getUser()->getId() != $this->getUser()->getId()){

            // Does teacher lack WRITE+ permission to class folder?
            $classFolder = $participant->getAssessable()->getBaseContext()->getFolder();
            if(!$this->getAccess()->isInstructor($this->getUser(), $classFolder)){
                throw new \UnexpectedValueException("Teachers may only mark tasks on classes where they have write permissions.");
            }
        }

        // Is student not a participant of Task ?
        if (!$response->getAssessable()->isParticipant($submittedFor)) {
            throw new \UnexpectedValueException("{$submittedFor->getFullName()} is not a participant in this task");
        }

        // Does the most recent response for the student already have the proposed status?
        if (empty($response->getId())) {
            $mostRecentResponse = $participant->findMostRecentResponse();
            if ($mostRecentResponse instanceof TaskResponse && $mostRecentResponse->getCompletionStatus() == $response->getCompletionStatus()) {
                throw new \UnexpectedValueException("{$submittedFor->getFullName()} already has status ({$mostRecentResponse->getCompletionStatus()}) for this task");
            }
        }

        // Is the student self-assessing ?
        if ($submittedBy->getId() == $submittedFor->getId()) {

            // Is the task not self-assessable ?
            if (!$response->getAssessable()->isSelfAssessable()) {
                throw new \UnexpectedValueException("Students may not self-assess a task if the task is not self-assessable");
            }

            // Is the task already marked by the teacher as complete ?
            if ($mostRecentResponse && !$mostRecentResponse->isSelfAssessed() && $mostRecentResponse->getMarkedComplete()) {
                throw new \UnexpectedValueException("Students may not change a task completion status if the teacher has marked it complete.");
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


}
