<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use DateTimeInterface;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\Search\Api\Hydrator\Translator\FullResultToArray;
use Schoolbox\Search\Api\Source\ISource;
use Schoolbox\UserMapper;

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
        private UserMapper $userMapper,
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
        $filter  = $this->buildSearchFilter($this->requestQuery['filter'] ?? null);
        $limit   = (int)($this->requestQuery['limit'] ?? self::MAX_RESULT_LIMIT);
        if (mb_strlen($keyword) >= 2) {
            $cursor = new Cursor\Cursor(
                $this->source,
                $keyword,
                $filter,
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
                // Get next set of raw results, and the cursor for the next
                // iteration.
                $nextRawResults = $cursor->retrieveResults($requestCursor);
                $requestCursor  = $cursor->next($nextRawResults);

                // No raw results retrieved:
                // We're at the end of the result set, so break.
                if (count($nextRawResults) === 0) {
                    break;
                }

                // Otherwise: hydrate raw results into full results,
                // then add them to the full result set.
                $fullResults = array_merge(
                    $fullResults,
                    $this->hydrator->hydrate($nextRawResults->getAll(), $this->user)
                );

                // Too many full results now?
                // Slice off excess results, get a cursor to the last remaining
                // full result, then break.
                if (count($fullResults) > $limit) {
                    $fullResults    = array_slice($fullResults, 0, $limit);
                    $lastFullResult = $fullResults[array_key_last($fullResults)];
                    $requestCursor  = $cursor->next(new Collection([$lastFullResult->rawResult]));
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

    /**
     * Build a search filter data object, for passing into the cursor.
     *
     * @param string|null $filterQueryString The filter from the query string, if any.
     * @return SearchFilter\DataObject
     * @throws MapperException if the user could not be found
     */
    private function buildSearchFilter(?string $filterQueryString): SearchFilter\DataObject
    {
        if (!isset($filterQueryString)) {
            return new SearchFilter\DataObject(null, null, null, null);
        }
        $decodedFilter = json_decode($filterQueryString, true);

        $types = null;
        if (is_array($decodedFilter['types'] ?? null)) {
            $types = $decodedFilter['types'];
        }

        $fromDate = null;
        if (isset($decodedFilter['date']['from'])) {
            $fromDate = new Alaress_Date_Format($decodedFilter['date']['from'], DateTimeInterface::RFC3339);
        }

        $toDate = null;
        if (isset($decodedFilter['date']['to'])) {
            $toDate = new Alaress_Date_Format($decodedFilter['date']['to'], DateTimeInterface::RFC3339);
        }

        $creator = null;
        if (isset($decodedFilter['creator']['id'])) {
            $creator = $this->userMapper->findById($decodedFilter['creator']['id']);
        }

        return new SearchFilter\DataObject($types, $fromDate, $toDate, $creator);
    }
}
