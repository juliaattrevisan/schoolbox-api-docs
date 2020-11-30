<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Mapper;

/**
 * Feed for the student's learning moment feed.
 *
 * This includes:
 * * Evidence
 * * Badges
 *
 * It should be cursored just as the learning moment feed is.
 */
class StudentFeed
{
    private const DEFAULT_LIMIT = 10;


    /**
     * @var Mapper[]
     */
    private array $mappers;


    /**
     * How should this work?
     * My thought:
     *
     * Supply at least one mapper
     * Each mapper will require:
     * * order by columns (these must be equivalent for each mapper: i.e. the
     *   first column of each order for each mapper must be the same type)
     * * a discriminator field (so I know which mapper a result came from)
     */
    public function __construct(Mapper ...$mappers)
    {
        $this->mappers = $mappers;
    }


    public function addCursor(string $discriminator, ICursor $cursor): self
    {
        return $this;
    }


    /**
     *
     */
    private function buildBaseQuery(): string
    {
        $queryParts = [];
        $params     = [];
        foreach ($this->mappers as $i => $mapper) {
            $query = "
                SELECT
                    {$mapper->getDatabase()->quoteIdentifier($this->buildDiscriminator($mapper))},
                    {$mapper->getDatabase()->quoteIdentifier($mapper->getIdentifierColumn())} AS id
                    -- Add each order by field for this mapper here
                FROM {$mapper->getDatabase()->quoteIdentifier($mapper->getTable())}
                ORDER BY {$this->buildOrderByPart($mapper)}
                -- Build the cursor's where part here: that's going to be difficult
                -- Maybe it'd be easier to just pass multiple cursors into an object
                -- then the composite cursor would be responsible for managing
                -- limits for each sub-cursor.
                -- Also: what will the encoded cursor look like?
                LIMIT :limit{$i}
            ";
            $queryParts[] = $query;
        }

        $baseQuery = implode(' UNION ALL', $queryParts);

        // Add the overall where parts here
        return $baseQuery;
    }


    private function buildDiscriminator(Mapper $mapper): string
    {
        return get_class($mapper);
    }


    private function buildOrderByPart(Mapper $mapper): string
    {
        return
            "{$mapper->getDatabase()->quoteIdentifier($mapper->getIdentifierColumn())} "
            . $mapper->getDatabase()->quoteIdentifier(Mapper::SORT_ORDER_ASC);
    }
}
