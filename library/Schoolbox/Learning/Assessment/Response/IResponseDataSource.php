<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\User;

/**
 * Interface for all assessment response sources.
 */
interface IResponseDataSource
{
    /**
     * Does this class recognize this type of assessable?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function recognizes(IAssessable $assessment);

    /**
     * Returns responses for this assessment.
     *
     * @param IAssessable $assessment
     * @param User        $student If set, only return this student's responses
     * @return Collection|IResponse[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null);

    /**
     * Returns responses for all these assessments.
     *
     * @param Collection|IAssessable[] $assessments
     * @param User                     $student If set, only return this student's responses
     * @return Collection|IResponse[][]
     */
    public function findForAssessmentCollection(Collection $assessments, User $student = null);

    /**
     * Returns responses that this student has submitted, or that have been
     * submitted on their behalf.
     *
     * @param User $student
     * @return mixed
     */
    public function findForStudent(User $student);

    /**
     * Saves a response.
     *
     * @param IResponse $response
     */
    public function save(IResponse $response);

    /**
     * Soft-deletes a response.
     *
     * @param IResponse $response
     */
    public function delete(IResponse $response);
}
