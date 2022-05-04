<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\YearLevel\Mapper;

class Controller extends Base
{

    protected IConnector $dbMapper;
    protected Helper $assessmentHelper;
    /**
     * Controller constructor.
     *
     */
    public function __construct(Mapper $mapper)
    {
        $this->assessmentHelper = new Helper($mapper);
    }

    /**
     * Returns an assessment object
     *
     * @api /api/assessment/{assessmentId}
     * @link TODO:
     */
    public function apiAssessmentGet(string $assessmentId)
    {
        if (!is_numeric($assessmentId)) {
            $problem = (new ApiProblem('Assessment not found, ID must be a number'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        //get all assessment info for object
        $output = $this->assessmentHelper->getAssessment($assessmentId);

        /**
         * build the whole thing
         */
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}
