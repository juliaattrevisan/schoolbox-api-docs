<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\Search\Api\Source\ISource;

/**
 * Controller for the search API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param ISource   $source   Source for raw search results.
     * @param IHydrator $hydrator Hydrates raw search results to full results.
     */
    public function __construct(
        private ISource $source,
        private IHydrator $hydrator,
    ) {
    }

    /**
     * Search for all results which match a keyword.
     *
     * @api GET /api/search
     * @since 23.1
     *
     * @return void
     */
    public function getSearch(): void
    {
        $keyword = (string)($this->requestQuery['keyword'] ?? '');
        if (mb_strlen($keyword) >= 2) {
            $cursor = new Cursor\Cursor(
                $this->source,
                $keyword,
                new Filter\DataObject(null, null, null, null),
                3,
            );
            $rawResults  = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);
            $fullResults = $this->hydrator->hydrate($rawResults->getAll());

            // TODO apply filter

            $this->response = $this->responseAsJson(
                [
                    'data'     => $fullResults,
                    'metadata' => [
                        'count'  => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'current' => null,
                            'next'    => $cursor->next($rawResults),
                        ],
                    ],
                ]
            );
        } else {
            $this->sendJsonError('Search keyword must be at least 2 characters', 400);
        }
    }
}
