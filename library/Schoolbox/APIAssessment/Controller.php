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
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment;

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
        switch (AssessmentType::objectToString($assessment)) {
            case 'quiz':
                $output = (new \Schoolbox\Learning\Assessment\Quiz\ModalToArray())($assessment);
                break;
            case 'dueWork':
                $output = (new \Schoolbox\Learning\Assessment\DueWork\ModalToArray())($assessment);
                break;
            case 'lti':
                $output = (new \Schoolbox\Learning\Assessment\Lti\ModalToArray())($assessment);
                break;
            case 'project':
                $output = (new \Schoolbox\Learning\Assessment\Project\ModalToArray())($assessment);
                break;
            case 'task':
                $output = (new \Schoolbox\Learning\Assessment\Task\ModalToArray())($assessment);
                break;
            default:
                $output = (new \Schoolbox\Learning\Assessment\ModelToArray())($assessment);
        }

        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}
