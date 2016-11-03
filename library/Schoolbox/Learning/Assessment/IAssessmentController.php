<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;


use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment\Participant;

interface IAssessmentController
{
    /**
     * @api   GET /learning/assessments/:assessmentId
     * @level 165
     *
     * @param IAssessable $assessment
     * @throws NotFound
     * @return void
     */
    public function getAssessment(IAssessable $assessment);

    /**
     * @api   POST /learning/assessments/:assessmentId
     * @level 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function postUpdateAssessment(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/modify
     * @level 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getUpdateAssessmentForm(IAssessable $assessment);

    /**
     * @api   POST /learning/assessments/:assessmentId/delete
     * @level 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/modify
     * @level 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getStatus(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function getSummary(Participant $participant);

    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function getCreateResponseForm(Participant $participant);

    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function postCreateResponse(Participant $participant);

}
