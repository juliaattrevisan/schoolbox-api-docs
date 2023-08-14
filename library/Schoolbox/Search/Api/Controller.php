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
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\FullResult;
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
     * Number of search results to return within a folder.
     */
    private const RESULT_LIMIT_WITHIN_FOLDER = 3;

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
        $filter  = $this->buildSearchFilter($this->requestQuery['filter'] ?? null, null);
        $limit   = (int)($this->requestQuery['limit'] ?? self::MAX_RESULT_LIMIT);
        if (mb_strlen($keyword) >= 2) {
            ['results' => $results, 'nextCursor' => $nextCursor] = $this->populateResultSet($keyword, $filter, $limit);

            $translatedResults       = [];
            $translatedFolderResults = [];
            foreach ($results as $result) {
                $resultKey = $result->type . '.' . $result->id;
                if ($result->folder) {
                    // Translate this into a folder result?
                    // Inject $translatedResult into the results?
                    // Load three folder results, then the next cursor?
                    if (!isset($translatedFolderResults[$result->folder->getId()])) {
                        $rawFolderResult = new RawResult(
                            $result->folder->getId(),
                            $result->folder->getTitle(),
                            // Which kind of page?
                            // TODO need to populate subtype
                            'page',
                            $result->relevance,
                        );
                        [$translatedFolderResult] = $this->hydrator->hydrate(
                            $keyword,
                            [$rawFolderResult],
                            $this->getUser(),
                        );
                        $translatedFolderResults[$result->folder->getId()] = $translatedFolderResult;
                        $translatedResults[$resultKey] ??= call_user_func($this->fullResultTranslator, $translatedFolderResult);

                        // Add search results for this folder
                        ['results' => $folderResults, 'nextCursor' => $folderNextCursor] = $this->populateResultSet(
                            $keyword,
                            $this->buildSearchFilter($this->requestQuery['filter'] ?? null, $result->folder),
                            self::RESULT_LIMIT_WITHIN_FOLDER,
                        );
                        $translatedResults[$resultKey]['_subResults'] = [
                            'data' => array_map($this->fullResultTranslator, $folderResults),
                            'metadata' => [
                                'count'  => null,
                                'cursor' => [
                                    'current' => null,
                                    'next'    => $folderNextCursor,
                                ],
                            ]
                        ];
                    }
                } else {
                    $translatedResults[$resultKey] ??= call_user_func($this->fullResultTranslator, $result);
                }
            }
            $translatedResults = array_values($translatedResults);

            // Finally output the response
            // Non-folder requests: find items in folders, and create responses
            // for their folders
            $this->response = $this->responseAsJson(
                [
                    'data'     => $translatedResults,
                    'metadata' => [
                        'count'  => null,
                        'cursor' => [
                            'current' => null,
                            'next'    => $nextCursor,
                        ],
                    ],
                ]
            );
        } else {
            $this->sendJsonError('Search keyword must be at least 2 characters', 400);
        }
    }

    /**
     * Search for all results which match a keyword within a folder.
     *
     * @api GET /api/search/folder/{folderId}
     * @since 23.1
     *
     * @return void
     */
    public function getSearchWithinFolder(Folder\Model $folder): void
    {
        $keyword = (string)($this->requestQuery['keyword'] ?? '');
        $filter  = $this->buildSearchFilter($this->requestQuery['filter'] ?? null, $folder);
        $limit   = (int)($this->requestQuery['limit'] ?? self::MAX_RESULT_LIMIT);
        if (mb_strlen($keyword) >= 2) {
            ['results' => $results, 'nextCursor' => $nextCursor] = $this->populateResultSet($keyword, $filter, $limit);

            // Finally output the response
            $this->response = $this->responseAsJson(
                [
                    'data'     => array_map($this->fullResultTranslator, $results),
                    'metadata' => [
                        'count'  => null,
                        'cursor' => [
                            'current' => null,
                            'next'    => $nextCursor,
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
     * @param string|null       $filterQueryString The filter from the query string, if any.
     * @param Folder\Model|null $folder            If provided, the folder to filter search results to.
     *                                             (This is never provided via the query string.)
     * @return SearchFilter\DataObject
     * @throws Exception if the filter has invalid data
     * @throws NotFound  if the creator is set, and cannot be found
     */
    private function buildSearchFilter(?string $filterQueryString, ?Folder\Model $folder): SearchFilter\DataObject
    {
        if (!isset($filterQueryString)) {
            return new SearchFilter\DataObject(null, null, null, null, $folder);
        }
        $decodedFilter = json_decode($filterQueryString, true);

        $types = null;
        if (isset($decodedFilter['types'])) {
            if (is_array($decodedFilter['types']) && array_is_list($decodedFilter['types'])) {
                $types = $decodedFilter['types'];
            } else {
                throw new Exception('Invalid filter: types is not a list of valid types');
            }
        }

        $fromDate = null;
        if (isset($decodedFilter['date']['from'])) {
            try {
                $fromDate = new Alaress_Date_Format($decodedFilter['date']['from'], DateTimeInterface::RFC3339);
            } catch (InvalidArgumentException $e) {
                throw new Exception('Invalid filter: date.from is not a RFC3339 date', 0, $e);
            }
        }

        $toDate = null;
        if (isset($decodedFilter['date']['to'])) {
            try {
                $toDate = new Alaress_Date_Format($decodedFilter['date']['to'], DateTimeInterface::RFC3339);
            } catch (InvalidArgumentException $e) {
                throw new Exception('Invalid filter: date.to is not a RFC3339 date', 0, $e);
            }
        }

        $creator = null;
        if (isset($decodedFilter['creator']['id'])) {
            if (!is_int($decodedFilter['creator']['id'])) {
                throw new Exception('Invalid filter: creator.id is not an integer');
            }
            try {
                $creator = $this->userMapper->findById($decodedFilter['creator']['id']);
            } catch (MapperException $e) {
                // Can't search for things created by an unknown user:
                // raise an error?
                throw new NotFound('Invalid filter: creator.id does not match known user', 0, $e);
            }
        }

        return new SearchFilter\DataObject($types, $fromDate, $toDate, $creator, $folder);
    }

    /**
     * Build a result set from the keyword, filter and limit.
     *
     * @param string                  $keyword
     * @param SearchFilter\DataObject $filter
     * @param int                     $limit
     * @return array{results: array<FullResult>, nextCursor: string}
     */
    private function populateResultSet(string $keyword, SearchFilter\DataObject $filter, int $limit): array
    {
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
        $nextCursor    = $this->requestQuery['cursor'] ?? null;
        while (count($fullResults) < $limit && $i++ < 100) {
            // Get next set of raw results, and the cursor for the next
            // iteration.
            $nextRawResults = $cursor->retrieveResults($nextCursor);
            $nextCursor  = $cursor->next($nextRawResults);

            // No raw results retrieved and no cursor?
            // We're at the end of the result set, so break.
            if (!isset($nextCursor) && count($nextRawResults) === 0) {
                break;
            }

            // Otherwise: hydrate raw results into full results,
            // then add them to the full result set.
            $fullResults = array_merge(
                $fullResults,
                $this->hydrator->hydrate($keyword, $nextRawResults->getAll(), $this->user)
            );

            // Too many full results now?
            // Slice off excess results, get a cursor to the last remaining
            // full result, then break.
            if (count($fullResults) > $limit) {
                $fullResults    = array_slice($fullResults, 0, $limit);
                $lastFullResult = $fullResults[array_key_last($fullResults)];
                $nextCursor     = $cursor->next(new Collection([$lastFullResult->rawResult]));
                break;
            }
        }
        return ['results' => $fullResults, 'nextCursor' => $nextCursor];
    }
}
