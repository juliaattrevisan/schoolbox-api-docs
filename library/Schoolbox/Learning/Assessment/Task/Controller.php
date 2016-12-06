<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Completion\MapperProxy as TaskResponseDataSource;
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
        $twigData = [
            'type'        => Assessment\AssessmentType::objectToString($assessment),
            'markScheme'  => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment),
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
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($task)->add([ $task->getTitle(), '' ]),
            'assessment'  => $task,
            'preview'     => $previewFragment,
            'currentUser' => $this->getUser(),
            'student'     => $participant->getUser(),
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
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function postCreateResponse(Participant $participant)
    {
        // Don't redirect to /task/start/:oldTaskId - students must mark tasks complete themselves

        // Params:
        // status: the status they want to set response to
        // and that's it - an existing response will never be updated
        $this->assertParticipantAccess($participant);

        $response = (new Completion\Model())
            ->setAssessable($participant->getAssessable())
            ->setSubmittedOnBehalfOf($participant->getUser())
            ->setSubmittedBy($this->getUser())
            // TODO figure out whether to set create time as now, or set it as a created timestamp in mapper
            ->setCreatedAt(\Alaress_Date_Format::now())
            ->markAs($this->getRequest()->post->getInt('status'))
        ;

        $response->assertSaveability();

        // And save
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
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $defaultReturnUrl = '')
    {
        return $defaultReturnUrl;
    }
}
