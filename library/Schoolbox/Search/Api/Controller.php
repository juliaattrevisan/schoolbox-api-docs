<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use LogicException;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\Search\Api\Hydrator\Translator\FullResultToArray;
use Schoolbox\Search\Api\SearchFilter\DataObject;
use Schoolbox\Search\Api\Source\ISource;
use Schoolbox\UserMapper;

/**
 * Controller for the search API.
 */
class Controller extends Base
{
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
     * @param IConnector        $connector
     */
    public function __construct(
        private ISource $source,
        private IHydrator $hydrator,
        private FullResultToArray $fullResultTranslator,
        private UserMapper $userMapper,
        private Folder\Mapper $folderMapper,
        private IConnector $connector,
    ) {
    }

    /**
     * Search for all results which match a keyword.
     *
     * @return void
     * @throws NotFound
     * @throws RouteNotFound
     * @api GET /api/search
     * @since 23.1
     *
     */
    public function getSearch(): void
    {
        $keyword = trim((string)$this->requestQuery->get('keyword', ''));
        $dataObjectFactory = new SearchFilter\DataObjectFactory($this->userMapper);
        $filter = $dataObjectFactory->build($this->requestQuery->get('filter'), null);
        $limit = (int)$this->requestQuery->get(
            'limit',
            $this->getConfigLoader()->get(ConfigMapper::NAME_ADVANCED_SEARCH_RESULT_BATCH_SIZE)
        );
        $cursor = null;

        if (mb_strlen($keyword) >= self::MIN_CHARACTER_LIMIT) {
            ['results' => $results, 'nextCursor' => $nextCursor] = $this->populateResultSet(
                $keyword,
                $filter,
                $limit,
                $this->requestQuery->get('cursor')
            );

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

                    // The decision procedure here must match that in Page/IndexBuilder->buildTypeExpression().
                    $folderResultType = 'page.';
                    if ($folder->hasCourseSpecification()) {
                        $folderResultType .= 'course';
                    } elseif ($folder->hasUnitSpecification()) {
                        $folderResultType .= 'unit';
                    } elseif (count($folder->getCodes())) {
                        $folderResultType .= 'class';
                    } elseif ($folder->isGroup()) {
                        $folderResultType .= 'group';
                    } elseif ($folder->hasHomepage()) {
                        $folderResultType .= 'homepage';
                    } else {
                        $folderResultType .= 'folder';
                    }

                    $folderResultKey = $folderResultType . '.' . $folderResultId;
                    if (!isset($translatedResults[$folderResultKey])) {
                        // Finagle a raw result for the folder, and hydrate that
                        // into a full result.
                        $rawFolderResult = new Cursor\RawResult(
                            (string)$folderResultId,
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
                            'url' => $this->routeGenerator->generate(
                                'searchApi.getSearchWithinFolder',
                                ['folderId' => $folder->getId()]
                            )
                        ]
                    ];
                    $translatedResults[$folderResultKey]['_subResults']['data'][] = $translatedResult;

                    if ($nextCursor === null) {
                        // If there are no more results on the main query, there are no more results on the folder queries too.
                        $translatedResults[$folderResultKey]['_subResults']['metadata']['cursor']['next'] = null;
                    } else {
                        // Use the cursor object to build a pointer to the next
                        // result within this page
                        $cursor ??= new Cursor\Cursor($this->getUser(), $this->source, $keyword, $filter, $limit);
                        $translatedResults[$folderResultKey]['_subResults']['metadata']['cursor']['next'] = $cursor->next(
                            new Collection([$result])
                        );
                    }
                } else {
                    $translatedResults[$resultKey] = $translatedResult;
                }
            }

            // If $nextCursor is null, there are no more results on the main query, and so we can be sure that there
            // are no more results on the folder queries too (this case has been dealt with above, inside the foreach loop).
            // If $nextCursor is not null, there are possibly more results on the main query (possibly, not definitely,
            // to account for the case where the total number of results equals MAX_RESULT_LIMIT), and so possibly more
            // sub-results in each folder-group.
            if ($nextCursor !== null) {
                // Do some more work to determine whether more sub-results exist for each folder-group of results.
                $moreResultsExistByResultKey = $this->getMoreResultsExistByResultKey($keyword, $filter, $translatedResults);
                foreach ($translatedResults as $resultKey => $translatedResult) {
                    if (
                        // The array key will only exist for resultKeys corresponding to results having sub-results.
                        array_key_exists($resultKey, $moreResultsExistByResultKey) &&
                        !$moreResultsExistByResultKey[$resultKey]
                    ) {
                        $translatedResults[$resultKey]['_subResults']['metadata']['cursor']['next'] = null;
                    }
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
     * @param Folder\Model $folder
     * @return void
     * @throws NotFound
     * @api GET /api/search/folder/{folderId}
     * @since 23.1
     */
    public function getSearchWithinFolder(Folder\Model $folder): void
    {
        $keyword = trim($this->requestQuery->get('keyword', ''));
        $dataObjectFactory = new SearchFilter\DataObjectFactory($this->userMapper);
        $filter = $dataObjectFactory->build($this->requestQuery->get('filter'), $folder);
        $limit = (int)$this->requestQuery->getInt(
            'limit',
            $this->getConfigLoader()->get(ConfigMapper::NAME_ADVANCED_SEARCH_RESULT_BATCH_SIZE)
        );

        if (mb_strlen($keyword) >= 2) {
            ['results' => $results, 'nextCursor' => $nextCursor] = $this->populateResultSet(
                $keyword,
                $filter,
                $limit,
                $this->requestQuery->get('cursor')
            );

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
     * Return an array like the following:
     *    [
     *       'page.unit.14'    => 1, // More results exist for this result key.
     *       'page.course.120' => 0, // No more results exist for this result key.
     *       ...
     *    ]
     *
     * Method:
     *    The array above is obtained by running a query like the following.
     *    The purpose of doing this in single query instead of many queries is to reduce the load on the server, and
     *    reduce search query reduce response times.
     *    SELECT
     *       IF(
     *          EXISTS([search within folder 1 beyond cursor]),
     *          1,
     *          0
     *       ) AS `page.unit.14`,
     *       IF(
     *          EXISTS([search within folder 2 beyond cursor]),
     *          1,
     *          0
     *       ) AS `page.course.120`,
     *       ...
     *    );
     * @param string     $keyword
     * @param DataObject $filter
     * @param array      $translatedResults
     * @return array
     */
    private function getMoreResultsExistByResultKey(string $keyword, DataObject $filter, array $translatedResults): array
    {
        if (count($translatedResults) === 0) {
            return [];
        }

        $subqueries = [];
        $params = [];
        $n = 0;

        foreach ($translatedResults as $resultKey => $translatedResult) {
            if (!array_key_exists('_subResults', $translatedResult)) {
                // This translated result does not have sub-results, so we are not interested.
                continue;
            }

            // String $resultKey key will contain three tokens.  Examples: 'page.homepage.80', 'page.course.100'.
            $tokens = explode('.', $resultKey);
            $folderId = $tokens[count($tokens) - 1];

            try {
                $filter->folder = $this->folderMapper->findById($folderId);
            } catch (MapperException) {
                // Folder deleted or not found.  Skip.
                continue;
            }

            $cursorCode = $translatedResult['_subResults']['metadata']['cursor']['next'];
            $codec = new Cursor\Codec();
            $cursor = $codec->decode($cursorCode);

            [$subquery, $subqueryParams] = $this->source->getMoreResultsExistQueryAndParams($this->getUser(), $keyword, $filter, $cursor, (string)$n++);

            $subqueries[] = $subquery . " AS `$resultKey`";

            $params = array_merge($params, $subqueryParams);
        }

        if (count($subqueries) === 0) {
            return [];
        }

        $rows = $this->connector->executeQuery('SELECT ' . implode(",\n", $subqueries), $params)->fetchAll();

        if (count($rows) !== 1) {
            throw new LogicException('Expected one row from query.');
        }

        return $rows[0];
    }

    /**
     * Build a result set from the keyword, filter and limit.
     *
     * @param string                  $keyword
     * @param SearchFilter\DataObject $filter
     * @param int                     $limit
     * @param string|null             $cursorCode
     * @return array{results: array<FullResult>, nextCursor: string|null}
     */
    private function populateResultSet(string $keyword, SearchFilter\DataObject $filter, int $limit, ?string $cursorCode): array
    {
        // Instead of iteratively backfilling the result set with more results
        // if it hasn't reached the limit:
        // allow the cursor itself to fetch more results than the limit, and
        // slice results off of the limit if there's too many.
        $cursor = new Cursor\Cursor(
            $this->getUser(),
            $this->source,
            $keyword,
            $filter,
            $limit * 10,
        );

        // Fetch results from cursor and hydrate
        $nextRawResults = $cursor->retrieveResults($cursorCode);
        $nextCursor     = $cursor->next($nextRawResults);
        $fullResults    = $this->hydrator->hydrate($keyword, $filter, $nextRawResults->getAll(), $this->user);

        // Too many full results now?
        // Slice off excess results, and get a cursor to the last remaining
        // full result.
        if (count($fullResults) > $limit) {
            $fullResults    = array_slice($fullResults, 0, $limit);
            $lastFullResult = $fullResults[array_key_last($fullResults)];
            $nextCursor     = $cursor->next(new Collection([$lastFullResult->rawResult]));
        } elseif (isset($nextCursor)) {
            // If there's no more than $limit raw results: there can't possibly
            // be more results left, because hydration can't add results
            if (count($nextRawResults) <= $limit) {
                $nextCursor = null;
            } else {
                // Ask the cursor if there's actually any results left:
                // if there are none, clear the next cursor so that the user is
                // not prompted for more results when we know there's none.
                $previewCursor  = new Cursor\Cursor($this->getUser(), $this->source, $keyword, $filter, 1);
                $previewResults = $previewCursor->retrieveResults($nextCursor);
                if (count($previewResults) === 0) {
                    $nextCursor = null;
                }
            }
        }

        return ['results' => $fullResults, 'nextCursor' => $nextCursor];
    }
}
