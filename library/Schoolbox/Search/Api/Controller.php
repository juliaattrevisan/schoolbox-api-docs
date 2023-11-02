<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\NotFound;
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
    private const MAX_RESULT_LIMIT = 20;

    /**
     * Minimum number of characters required to complete a search
     */
    private const MIN_CHARACTER_LIMIT = 2;


    /**
     * Constructor
     *
     * @param ISource           $source               Source for raw search results.
     * @param IHydrator         $hydrator             Hydrates raw search results to full results.
     * @param FullResultToArray $fullResultTranslator Translates full results to their JSON representation.
     * @param UserMapper        $userMapper           Finds the user model corresponding to the creator in the filter.
     * @param Folder\Mapper     $folderMapper         Fully hydrates the folder in a raw result, so that its type may be
     *                                                determined.
     */
    public function __construct(
        private ISource $source,
        private IHydrator $hydrator,
        private FullResultToArray $fullResultTranslator,
        private UserMapper $userMapper,
        private Folder\Mapper $folderMapper,
    ) {
    }

    /**
     * Search for all results which match a keyword.
     *
     * @return void
     * @throws NotFound
     * @api GET /api/search
     * @since 23.1
     *
     */
    public function getSearch(): void
    {
        $keyword = trim((string)$this->requestQuery->get('keyword', ''));
        $dataObjectFactory = new SearchFilter\DataObjectFactory($this->userMapper);
        $filter = $dataObjectFactory->build($this->requestQuery->get('filter'), null);
        $limit = (int)($this->requestQuery->get('limit', self::MAX_RESULT_LIMIT));
        $cursor = null;

        if (mb_strlen($keyword) >= self::MIN_CHARACTER_LIMIT) {
            ['results' => $results, 'nextCursor' => $nextCursor] = $this->populateResultSet($keyword, $filter, $limit);

            $translatedResults = [];
            foreach ($results as $result) {
                $resultKey        = $result->type . '.' . $result->id;
                $translatedResult = call_user_func($this->fullResultTranslator, $result);
                if ($result->folder) {
                    // Is this a result with a folder? If so: translate this
                    // into a folder result, and inject a page of results within
                    // that folder into the result.
                    // Do not add the folder result more than once, if there is
                    // more than one result for the same folder: the search
                    // results within the folder will contain a reasonable
                    // subset of results.
                    $folderResultId = $result->folder->getId();

                    // Determine the folder's result type
                    // TODO single point of control: this is in the query param
                    //      builder for pages as well
                    try {
                        $folder = $this->folderMapper->findById($folderResultId);
                    } catch (MapperException) {
                        // Somehow this was deleted? It shouldn't be, so ignore.
                        continue;
                    }
                    $folderResultType = 'page.';
                    if ($folder->hasCourseSpecification()) {
                        $folderResultType .= 'course';
                    } elseif ($folder->hasUnitSpecification()) {
                        $folderResultType .= 'unit';
                    } elseif (count($folder->getCodes())) {
                        $folderResultType .= 'class';
                    } elseif ($folder->hasHomepage()) {
                        $folderResultType .= 'homepage';
                    } else {
                        $folderResultType .= 'other';
                    }
                    $folderResultKey  = $folderResultType . '.' . $folderResultId;
                    if (!isset($translatedResults[$folderResultKey])) {
                        // Finagle a raw result for the folder, and hydrate that
                        // into a full result.
                        $rawFolderResult = new RawResult(
                            $folderResultId,
                            $result->folder->getTitle(),
                            $folderResultType,
                            $result->relevance,
                        );
                        $hydratedResults = $this->hydrator->hydrate(
                            $keyword,
                            $filter,
                            [$rawFolderResult],
                            $this->getUser(),
                        );

                        if (count($hydratedResults) === 0) {
                            // Could not hydrate this $rawFolderResult, likely because the user does not have access to the folder.
                            continue;
                        }

                        $folderResult = $hydratedResults[0];
                        if (!$folderResult) {
                            continue;
                        }

                        // Translate the folder result
                        $translatedResults[$folderResultKey] = call_user_func($this->fullResultTranslator, $folderResult);
                    }

                    // Add this result into the sub-results
                    $translatedResults[$folderResultKey]['_subResults'] ??= [
                        'data'     => [],
                        'metadata' => [
                            'count'  => null,
                            'cursor' => [
                                'current' => null,
                                'next'    => null,
                            ],
                        ]
                    ];
                    $translatedResults[$folderResultKey]['_subResults']['data'][] = $translatedResult;

                    // Use the cursor object to build a pointer to the next
                    // result within this page
                    $cursor ??= new Cursor\Cursor($this->source, $keyword, $filter, $limit);
                    $translatedResults[$folderResultKey]['_subResults']['metadata']['cursor']['next'] = $cursor->next(
                        new Collection([$result])
                    );
                } else {
                    $translatedResults[$resultKey] = $translatedResult;
                }
            }
            $translatedResults = array_values($translatedResults);

            // Finally output the response
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
            $this->sendJsonError('Search keyword must be at least ' . self::MIN_CHARACTER_LIMIT . ' characters', 400);
        }
    }

    /**
     * Search for all results which match a keyword within a folder.
     *
     * @return void
     * @throws NotFound
     * @api GET /api/search/folder/{folderId}
     * @since 23.1
     *
     */
    public function getSearchWithinFolder(Folder\Model $folder): void
    {
        $keyword = trim($this->requestQuery->get('keyword', ''));
        $dataObjectFactory = new SearchFilter\DataObjectFactory($this->userMapper);
        $filter = $dataObjectFactory->build($this->requestQuery->get('filter'), $folder);
        $limit = (int)($this->requestQuery->get('limit', self::MAX_RESULT_LIMIT));

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
            $this->sendJsonError('Search keyword must be at least ' . self::MIN_CHARACTER_LIMIT . ' characters', 400);
        }
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
        $nextCursor    = $this->requestQuery->get('cursor');
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
                $this->hydrator->hydrate($keyword, $filter, $nextRawResults->getAll(), $this->user)
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
