<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\ModelToArray;

class Controller extends Base
{


    /**
     * Controller constructor.
     *
     */
    public function __construct()
    {
    }

    /**
     * Returns an assessment object
     *
     * @api /api/assessment/{assessmentId}
     * @link TODO:
     */
    public function apiAssessmentGet(IAssessable $assessment)
    {
        //get all assessment info for object

        /**
         * build the whole thing
         */

        $output = (new ModelToArray())($assessment);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}
