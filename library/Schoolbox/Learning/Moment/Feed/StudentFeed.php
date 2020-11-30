<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
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
class StudentFeed implements ICursor
{
    /**
     * A list of mappers to cursor over.
     *
     * @var Mapper[]
     */
    private array $mappers;


    /**
     * The maximum number of results which may be retrieved at once from this
     * cursor.
     */
    private int $limit;


    /**
     * The order in which results are retrieved from this mapper.
     */
    private array $order;


    /**
     * A codec for encoding a result set into a cursor, and decoding a cursor
     * into a pointer to a result set.
     *
     * Defaults to `\Alaress\Codec\Json\Base64`.
     */
    private ICodec $codec;


    /**
     * Constructor
     *
     * @param int         $limit Fetch only this number of results at a time.
     * @param ICodec|null $codec If provided, use this codec to translate the
     *                           cursor.
     *                           Defaults to `\Alaress\Codec\Json\Base64`.
     */
    public function __construct(int $limit, ?ICodec $codec = null)
    {
        $this->limit = $limit;
        $this->codec = $codec ?? new Base64();
    }


    public function addMapper(string $discriminator, Mapper $mapper, array $order): self
    {
        $this->mappers[$discriminator] = $mapper;
        $this->order[$discriminator]   = $order;
        return $this;
    }


    /**
     *
     */
    private function buildBaseQuery(): string
    {
        $queryParts = [];
        $params     = ['limit' => $this->limit];
        foreach ($this->mappers as $discriminator => $mapper) {
            $query = "
                SELECT
                    {$mapper->getDatabase()->quoteIdentifier($discriminator)},
                    {$mapper->getDatabase()->quoteIdentifier($mapper->getIdentifierColumn())} AS id
                    -- Add each order by field for this mapper here
                FROM {$mapper->getDatabase()->quoteIdentifier($mapper->getTable())}
                ORDER BY {$this->buildOrderByPart($mapper)}
                -- Build the cursor's where part here: that's going to be difficult
                -- Maybe it'd be easier to just pass multiple cursors into an object
                -- then the composite cursor would be responsible for managing
                -- limits for each sub-cursor.
                -- Also: what will the encoded cursor look like?
                LIMIT :limit
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
