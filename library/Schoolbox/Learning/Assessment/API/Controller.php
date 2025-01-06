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
     * The maximum number of assessments returned in response to an API request.
     */
    const MAX_LIMIT = 500;

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
            $this->response = $this->responseAsJson($problem)->withStatus(404);
            return;
        }

        //get all assessment info for object
        try {
            $output = $this->assessmentHelper->getAssessment($assessmentId);
        } catch (SchoolboxException $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(404)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(404);
            return;
        }

        /**
         * build the whole thing
         */
        $this->response = $this->responseAsJson($output);
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

        if ($limit <= 0) {
            $problem = (new ApiProblem('Limit should be a positive integer'))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return; //exit early if the limit is either 0 or a negative value
        }

        if ($limit > self::MAX_LIMIT) {
            $limit = self::MAX_LIMIT;
        }

        if (null === $filter) {
            $problem = (new ApiProblem('Filter must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(401);
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

        $this->response = $this->responseAsJson(
            [
                'data'  =>  $output,
                'metadata'  => [
                    'count' => $count,
                    'cursor' => end($output)['id'] ?? null
                ]
            ]
        );
    }
}
