<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;

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
     * @return Collection|IResponse[]
     */
    public function findForAssessment(IAssessable $assessment);
}
