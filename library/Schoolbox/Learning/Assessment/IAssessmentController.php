<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Web;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\User;

interface IAssessmentController
{

    /**
     * Retrieve a list of assessments that exist, by context
     *
     * NOTE: this is a debug route that is only available to supers, so don't go referencing this route in twigs...
     * It shows the functionality of the common "assessment component" logic for any context
     *
     * @api GET /learning/{contextType}/{contextId}/assessments?user&readOnly
     * @since 165
     *
     * @param AssessmentContext $context
     */
    public function getAssessmentsByContext(AssessmentContext $context);

    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @since 165
     *
     * @param AssessmentContext $context
     */
    public function getCreateAssessmentForm(AssessmentContext $context);

    /**
     * Create a new assessment in the given context
     *
     * @api   POST /learning/{:contextType}/{:contextId}/assessments
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws Web\Controller\Exception
     */
    public function postCreateAssessment(AssessmentContext $context);

    /**
     * @api   POST /learning/assessments/:assessmentId
     * @since 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function postUpdateAssessment(IAssessable $assessment);

    /**
     * @api   PATCH /learning/assessments/:assessmentId
     * @since 165
     *
     * @param IAssessable $assessment
     * @return mixed
     */
    public function patchUpdateAssessment(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/modify
     * @since 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getUpdateAssessmentForm(IAssessable $assessment);

    /**
     * @api   POST /learning/assessments/:assessmentId/delete
     * @since 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/modify
     * @since 165
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getStatus(IAssessable $assessment);

    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function getSummary(IAssessable $assessment, User $student): void;


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void;


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void;
}
