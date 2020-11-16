<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Cursor;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * A cursor over a result set retrieved by a mapper.
 */
class MapperCursor implements ICursor
{
    /**
     * The mapper from which results are retrieved.
     */
    private Mapper $mapper;


    /**
     * The maximum number of results which may be retrieved at once from this
     * cursor.
     */
    private int $limit;


    /**
     * The order in which results are retrieved from this mapper.
     */
    private string $order;


    /**
     * A codec for encoding a result set into a cursor, and decoding a cursor
     * into a pointer to a result set.
     */
    private ICodec $codec;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param string $order
     * @param int    $limit
     * @throws InvalidArgumentException if $order isn't one of this class' ORDER_* constants
     */
    public function __construct(
        Mapper $mapper,
        int $limit,
        string $order = Mapper::SORT_ORDER_ASC
    ) {
        $this->mapper = $mapper;

        if (!in_array($order, [Mapper::SORT_ORDER_ASC, Mapper::SORT_ORDER_ASC], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Order must be one of the %s::SORT_ORDER_* constants',
                    Mapper::class
                )
            );
        }
        $this->limit = $limit;
        $this->order = $order;

        // TODO a codec should be injected
        $this->codec = new Base64();
    }


    public function next(Collection $collection): ?string
    {
        $lastRecord = $collection->last();

        // The most basic version: sort by ID
        if (!$lastRecord instanceof Id) {
            return null;
        }

        // The next cursor points to the last record.
        return $this->codec->encode([
            'created_at' => $lastRecord->getCreatedAt()->getDatabase(),
            'id'         => $lastRecord->getId(),
        ]);
    }


    public function current(Collection $collection): ?string
    {
        // TODO: Implement current() method.
    }


    public function retrieveResultCount(): ?int
    {
        return $this->mapper->countByPredicates($this->buildPredicateBuilder());
    }


    public function retrieveResults(?string $encodedCursor): Collection
    {
        return $this->mapper->findByPredicates(
            $this->applyCursor($this->buildPredicateBuilder(), $encodedCursor),
            [
                'limit' => $this->limit,
                'order' => [
                    'created_at' => $this->order,
                    'id'         => $this->order,
                ],
            ]
        )->reindex();
    }


    /**
     * Which columns are ordered on?
     */
    private function retrieveOrderByProperties(): array
    {
        // Allow separate ordering here
        return ['created_at', 'id',];
    }


    /**
     * Create a predicate builder.
     *
     * @return Builder
     */
    protected function buildPredicateBuilder(): Builder
    {
        return $this->mapper->getPredicateBuilder();
    }


    /**
     * Apply a cursor to the predicate builder.
     *
     * @param Builder     $builder
     * @param string|null $cursor
     * @return Builder
     * @throws InvalidColumnException if somehow the query built references a column that doesn't exist
     */
    private function applyCursor(Builder $builder, ?string $cursor): Builder
    {
        if (isset($cursor) && $cursor !== '') {
            $lastId = $this->codec->decode($cursor);
            if (isset($lastId) && $lastId > 0) {
                if ($this->order === Mapper::SORT_ORDER_ASC) {
                    // Ascending: get only items greater than last ID
                    $builder->greaterThan($this->mapper->getIdentifierColumn(), $lastId);
                } elseif ($this->order ===  Mapper::SORT_ORDER_DESC) {
                    // Descending: get only items less than last ID
                    $builder->lessThan($this->mapper->getIdentifierColumn(), $lastId);
                }
            }
        }
        return $builder;
    }

}
