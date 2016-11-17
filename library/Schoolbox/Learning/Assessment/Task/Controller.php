<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Preview\Factory as PreviewFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Controller extends Assessment\Controller
{

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

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/task/status/staff.twig",
                $twigData
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
        $task = $participant->getAssessable();
        $previewFragment = [];

        if($task->getUrl()) {
            $previewFragment = (new PreviewFactory())->urlFragment($task->getUrl(), $this->getUser());
            //Just foward if we only have a URL
            if ($previewFragment['type'] == 'redirect' && !$task->getDescription()) {
                $this->getResponse()->redirect->temporaryRedirect($task->getUrl());
                return;
            }
            else if($foundHash = preg_match('/hash=([a-zA-Z0-9]+)/', $task->getUrl(), $matches)) {
                    $previewFragment['file'] = StorageMapper::getInstance()->findByHash($matches[1]);
                    $previewFragment['type'] = 'file';
            }
        }

        if($previewFragment['type'] == 'inline') {
            $previewFragment['fragment'] = $this->buildUnthemedHtmlOutput($previewFragment['twig']['template'], $previewFragment['twig']['data'])->getAsString();
        }

        $twigData = [ 'breadcrumbs' => $this->getBakery()->produceFromAssessment($task)->add([ $task->getTitle(), '' ]),
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
        // TODO redirect this to /task/start/:oldTaskId
        throw new NotFound();
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

    /**
     * Update particular fields of an existing IAssessable
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function patchUpdateAssessment(IAssessable $assessment)
    {
    }
}
