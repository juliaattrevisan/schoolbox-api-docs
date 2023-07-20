<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\API;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Helper $assessmentHelper Helper class for looking up assessments
     */
    public function __construct(private Helper $assessmentHelper)
    {
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
            return;
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
            return;
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
        $getFilter = $this->requestQuery->get('filter', '');
        if (is_string($getFilter)) {
            $filter = json_decode($this->requestQuery->get('filter', ''), true) ?? null;
        } elseif (is_array($getFilter)) {
            $filter = $getFilter;
        }
        $cursor = (int)$this->requestQuery->get('cursor', null);
        $limit = (int)$this->requestQuery->get('limit', 5);

        if (null === $filter) {
            $problem = (new ApiProblem('Filter must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }

        try {
            $this->assessmentHelper->validateFilter($filter);
            $output = $this->assessmentHelper->searchForAssessments($filter, $limit, $cursor);
            $count = $this->assessmentHelper->searchForAssessmentsCount($filter);
        } catch (SchoolboxException $e) {
            $this->sendJsonError('Failed to get assessments: ' . $e->getMessage(), 400);
            return;
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
