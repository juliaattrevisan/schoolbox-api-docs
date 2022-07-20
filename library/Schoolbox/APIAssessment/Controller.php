<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\YearLevel\Mapper;
use PDO;

class Controller extends Base
{
    protected Helper $assessmentHelper;
    protected Mapper $dbMapper;

    /**
     * Controller constructor.
     *
     */

    public function __construct(Mapper $mapper)
    {
        $this->assessmentHelper = new Helper($mapper);
        $this->dbMapper = $mapper;
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
        try {
            $output = $this->assessmentHelper->getAssessment($assessmentId);
        } catch (SchoolboxException $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        /**
         * build the whole thing
         */
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * @api GET /api/assessment
     * @link TODO
     */
    public function apiAssessmentSearch()
    {
        /**
         * check if filter is an array or string and use it like that
         */
        $filter = null;
        $getFilter = $this->request->query->get('filter', '');
        if (is_string($getFilter)) {
            $filter = json_decode($this->request->query->get('filter', ''), true) ?? null;
        } elseif (is_array($getFilter)) {
            $filter = $getFilter;
        }
        $cursor = (int)$this->request->query->get('cursor', null);
        $limit = (int)$this->request->query->get('limit', 5);

        if (null === $filter) {
            $problem = (new ApiProblem('Filter must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }

        try {
            $output = $this->assessmentHelper->searchForAssessments($filter, $limit, $cursor);
            $count = $this->assessmentHelper->searchForAssessmentsCount($filter);
        } catch (SchoolboxException $e) {
            $output = [];
            $count = 0;
        }

        /**
         * now we have the user id's that match the search result, create the standard user array with hydration.
         */

        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'data'  =>  $output,
                    'metadata'  => [
                        'count' => $count,
                        'cursor' => end($output)['id'] ?? null
                    ]
                ]
            )
        );
    }
}
