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
    private array $order;


    /**
     * A codec for encoding a result set into a cursor, and decoding a cursor
     * into a pointer to a result set.
     */
    private ICodec $codec;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param array  $order
     * @param int    $limit
     */
    public function __construct(
        Mapper $mapper,
        int $limit,
        array $order
    ) {
        $this->mapper = $mapper;
        $this->limit = $limit;
        $this->order = $order;

        // TODO a codec should be injected
        $this->codec = new Base64();
    }


    public function next(Collection $collection): ?string
    {
        $lastRecord = $collection->last();


        // The fields in the codec ought to match the fields in the last record
        $unencodedCursor = [];
        foreach ($this->order as $column => $orderBy) {
            $unencodedCursor[$column] = $this->mapper->getModelProperty(
                $lastRecord,
                $this->mapper->getPropertyFromColumn($column),
            );

            // Special case for date objects: convert to a database date
        }


        // The next cursor points to the last record.
        return $this->codec->encode($unencodedCursor);
    }


    public function current(Collection $collection): ?string
    {
        return null;
    }


    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        return $this->mapper->countByPredicates($this->buildPredicateBuilder());
    }


    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        return $this->mapper->findByPredicates(
            $this->applyCursor($this->buildPredicateBuilder(), $encodedCursor),
            [
                'limit' => $this->limit,
                'order' => $this->order,
            ]
        )->reindex();
    }


    /**
     * Create a predicate builder.
     *
     * This may be overridden by subclasses, in order to filter a response to
     * (for example) only items which belong to a particular user.
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
//        $subBuilder = $builder->emptyClone();
        if (isset($cursor) && $cursor !== '') {
            $decodedCursor = $this->codec->decode($cursor);

//            $nextBuilder   = null;

            // Create a set of predicate builders, one for each column in the
            // order by list.
            $builders         = [];
            $previousBuilders = [];
            foreach ($this->order as $column => $orderBy) {
                if ($orderBy === Mapper::SORT_ORDER_ASC) {
                    $thisBuilder = $builder->emptyClone()->greaterThan($column, $decodedCursor[$column]);
                } elseif ($orderBy === Mapper::SORT_ORDER_DESC) {
                    $thisBuilder = $builder->emptyClone()->lessThan($column, $decodedCursor[$column]);
                } else {
                    continue;
                }

                $builders[] = array_merge(
                    $previousBuilders,
                    [$thisBuilder]
                );
                $previousBuilders[] = $builder->emptyClone()->equals($column, $decodedCursor[$column]);
            }


            // Create a new one: any of $builders[] may match, but all within
            // each $builders[] must match
            $subBuilders = [];
            foreach ($builders as $subBuilderList) {
                $subSubBuilder = $builder->emptyClone();
                foreach ($subBuilderList as $subBuilderItem) {
                    $subSubBuilder = $subSubBuilder->all($subBuilderItem);
                }
                $subBuilders[] = $builder->emptyClone()->all($subSubBuilder);
            }

            $subBuilder = $builder->emptyClone();
            foreach ($subBuilders as $subBuilder2) {
                $subBuilder = $subBuilder->any($subBuilder2);
            }
            $subBuilder = $subBuilder->any($subBuilder);
            $builder->any($subBuilder);
        }


        // Just return the builder
        return $builder;
    }

}
