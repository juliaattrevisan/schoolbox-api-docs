<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\Search\Api\Hydrator\Translator\FullResultToArray;
use Schoolbox\Search\Api\Source\ISource;

/**
 * Controller for the search API.
 */
class Controller extends Base
{
    /**
     * Maximum number of search results to return per call.
     */
    private const MAX_RESULT_LIMIT = 10;

    /**
     * Constructor
     *
     * @param ISource   $source   Source for raw search results.
     * @param IHydrator $hydrator Hydrates raw search results to full results.
     */
    public function __construct(
        private ISource $source,
        private IHydrator $hydrator,
        private FullResultToArray $fullResultTranslator,
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
        $limit   = (int)($this->requestQuery['limit'] ?? self::MAX_RESULT_LIMIT);
        if (mb_strlen($keyword) >= 2) {
            $cursor = new Cursor\Cursor(
                $this->source,
                $keyword,
                // TODO create filter from search query
                new SearchFilter\DataObject(null, null, null, null),
                $limit,
            );

            // Fetch results from cursor and hydrate, until either:
            // - limit is reached
            // - the end of cursor is reached
            // This ensures there is a consistent number of results returned,
            // even if the hydrator filters out results.
            $i             = 0;
            $fullResults   = [];
            $requestCursor = $this->requestQuery['cursor'] ?? null;
            while (count($fullResults) < $limit && $i++ < 100) {
                $rawResults = $cursor->retrieveResults($requestCursor);

                // hydrate, then add to result set
                $fullResults = array_merge(
                    $fullResults,
                    $this->hydrator->hydrate($rawResults->getAll(), $this->user)
                );

                // Get cursor for next iteration.
                // No more results: break
                $requestCursor = $cursor->next($rawResults);
                if (!isset($requestCursor)) {
                    break;
                }
            }

            // Finally output the response
            $this->response = $this->responseAsJson(
                [
                    'data'     => array_map($this->fullResultTranslator, $fullResults),
                    'metadata' => [
                        'count'  => null,
                        'cursor' => [
                            'current' => null,
                            'next'    => $requestCursor,
                        ],
                    ],
                ]
            );
        } else {
            $this->sendJsonError('Search keyword must be at least 2 characters', 400);
        }
    }
}
