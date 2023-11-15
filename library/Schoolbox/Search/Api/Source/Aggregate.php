<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Source;

use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;

/**
 * Aggregate of search results from several sources.
 */
class Aggregate implements ISource
{
    /**
     * A list of sources to aggregate results from.
     *
     * @var ISource[]
     */
    private array $sources;

    /**
     * Constructor
     *
     * @param ISource ...$sources
     */
    public function __construct(ISource ...$sources)
    {
        $this->sources = $sources;
    }

    /**
     * @inheritdoc
     */
    public function search(string $keyword, FilterDataObject $filter, ?CursorDataObject $cursor, int $limit): array
    {
        return array_merge(
            ...array_map(
                fn ($source) => $source->search($keyword, $filter, $cursor, $limit),
                $this->sources,
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function getMoreResultsExistQueryAndParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        string $paramKeySuffix = ''
    ): array {
        return array_merge(
            ...array_map(
                   fn ($source) => $source->getMoreResultsExistQueryAndParams($keyword, $filter, $cursor, $paramKeySuffix),
                   $this->sources,
               ),
        );
    }
}
