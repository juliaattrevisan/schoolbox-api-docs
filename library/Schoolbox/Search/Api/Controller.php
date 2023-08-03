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
                // TODO create filter from result
                new SearchFilter\DataObject(null, null, null, null),
                $limit,
            );
            $rawResults  = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);
            $fullResults = $this->hydrator->hydrate($rawResults->getAll(), $this->user);

            // TODO if there's not enough results: fetch more until done

            $this->response = $this->responseAsJson(
                [
                    'data'     => array_map($this->fullResultTranslator, $fullResults),
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
