<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Preview\Factory as PreviewFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;
use \Schoolbox\Learning\Assessment\Task\Model as Task;

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
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param TaskResponseDataSource        $taskResponseDataSource
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        TaskResponseDataSource $taskResponseDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
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
            'user'        => $this->getUser(),
            'type'        => Assessment\AssessmentType::objectToString($assessment),
            'markScheme'  => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'  => $assessment,
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
     * @param Participant $participant
     */
    public function getSummary(Participant $participant)
    {
        /** @var Task $task */
        $task = $participant->getAssessable();
        $previewFragment = [];

        /* If there was a specified url, it could be to some external location
         * or even to a storage file on this server - use the PreviewFactory to
         * decide which one it is
         */
        if ($task->getUrl()) {
            $previewFragment = (new PreviewFactory())->urlFragment($task->getUrl(), $this->getUser());

            if ($previewFragment['type'] == 'redirect' && !$task->getDescription()) {

                /* Just forward if we only have a URL
                 */
                $this->getResponse()->redirect->temporaryRedirect($task->getUrl());
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
            elseif ($foundHash = preg_match('/hash=([a-zA-Z0-9]+)/', $task->getUrl(), $matches)) {

                /* Other regular files
                 *
                 * Download them as some kind of attachment
                 */
                $previewFragment['type'] = 'file';
                $previewFragment['file'] = StorageMapper::getInstance()->findByHash($matches[1]);
            }
            elseif ($previewFragment['type'] == 'inline') {

                /* Embedded content like youtube videos etc
                 */
                $previewFragment['fragment'] = $this->buildUnthemedHtmlOutput($previewFragment['twig']['template'], $previewFragment['twig']['data'])->getAsString();
            }
        }

        $twigData = [
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($task, $this->getUser())->add([ $task->getTitle(), '' ]),
            'assessment'   => $task,
            'hasWritePerm' => $this->getAccess()->getFolderAccess($this->getUser(), [$task->getBaseContext()->getFolder()->getId()]) >= UserAccess::ACCESS_WRITE,
            'preview'      => $previewFragment,
            'student'      => $participant->getUser(),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/task/summary/student.twig",
                $twigData
            )
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function getCreateResponseForm(Participant $participant)
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
     * @param Participant $participant
     * @var string[] $_POST
     * @var int      $_POST['status']    The proposed completion status
     * @return void
     */
    public function postCreateResponse(Participant $participant)
    {
        // Ensure the current session user is allowed to see the given participation
        $this->assertParticipantAccess($participant);

        // Did user mark (regardless of who they are) the task as incomplete ?
        $isMarkedComplete = $this->getRequest()->post->getInt('status') != TaskCompletion::STATUS_INCOMPLETE;

        $response = (new Response\Model())
            ->setAssessable($participant->getAssessable())
            ->setSubmittedOnBehalfOf($participant->getUser())
            ->setSubmittedBy($this->getUser())
            // TODO figure out whether to set create time as now, or set it as a created timestamp in mapper
            ->setCreatedAt(\Alaress_Date_Format::now())
            ->setMarkedComplete($isMarkedComplete);

        try{
            $this->assertSaveability($participant, $response);
        } catch (\UnexpectedValueException $e){
            $result['success']   = false;
            $result['message']   = $e->getMessage();
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
                        'assessmentId' => $participant->getAssessable()->getId(),
                        'studentId'    => $participant->getUser()->getId(),
                    ]
                )
            );
        }
    }

    /**
     * Ensure the current user has permission to create this response and the response state is valid
     *
     * @param Participant    $participant
     * @param Response\Model $response
     * @throws \UnexpectedValueException
     */
    protected function assertSaveability(Participant $participant, Response\Model $response)
    {
        // If not marking themselves, user must have write+ permission to class folder
        if($participant->getUser()->getId() != $this->getUser()->getId()){
            $classFolderId = $participant->getAssessable()->getBaseContext()->getId();
            if(Access::getInstance()->getFolderAccess($this->getUser(), [$classFolderId]) < UserAccess::ACCESS_WRITE){
                throw new \UnexpectedValueException("Teachers may only mark tasks on classes where they have write permissions.");
            }
        }
        $response->assertSaveability();
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
