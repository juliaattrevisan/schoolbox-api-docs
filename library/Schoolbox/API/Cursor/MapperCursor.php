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
     *
     * Defaults to `\Alaress\Codec\Json\Base64`.
     */
    private ICodec $codec;


    /**
     * Constructor
     *
     * @param Mapper      $mapper
     * @param int         $limit
     * @param array       $order
     * @param ICodec|null $codec If provided, use this codec to translate the
     *                           cursor.
     *                           Defaults to `\Alaress\Codec\Json\Base64`.
     */
    public function __construct(
        Mapper $mapper,
        int $limit,
        array $order,
        ?ICodec $codec = null
    ) {
        $this->mapper = $mapper;
        $this->limit  = $limit;
        $this->order  = $order;
        $this->codec  = $codec ?? new Base64();
    }


    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        $lastRecord = $collection->last();
        if (!isset($lastRecord)) {
            return null;
        }


        // The fields in the codec ought to match the fields in the last record
        $unencodedCursor = [];
        foreach ($this->order as $column => $orderBy) {
            // Use the hydrator to convert the property into a scalar
            $hydrator = $this->mapper->getColumnScalarHydrator($column);
            $property = $this->mapper->getModelProperty(
                $lastRecord,
                $this->mapper->getPropertyFromColumn($column),
            );
            $unencodedCursor[$column] = $hydrator->hydrate($property);
        }


        // The next cursor points to the last record.
        return $this->codec->encode($unencodedCursor);
    }


    /**
     * This cursor cannot reliably generate an encoded cursor to a result set,
     * so it will always return null.
     *
     * This is because it can't guarantee that the column in the final order
     * clause is unique, so it can't determine what the last result in the
     * previous cursor would have been.
     *
     * @param Collection $collection
     * @return null
     */
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
     * @param string|null $encodedCursor
     * @return Builder
     * @throws InvalidColumnException if somehow the query built references a
     *                                column which doesn't exist
     */
    private function applyCursor(Builder $builder, ?string $encodedCursor): Builder
    {
        if (isset($encodedCursor) && $encodedCursor !== '') {
            $decodedCursor = $this->codec->decode($encodedCursor);

            // Craft a query builder which will fetch results after the item
            // encoded in the cursor.
            // For example: if this cursor's order is "user_id asc, date desc, id asc"
            // and the encoded cursor contains "user_id=65, date=2020-12-12, id=50"
            // then the query builder must include results which match any of:
            // * user_id > 65
            // * user_id = 65 AND date < 2020-12-12
            // * user_id = 65 AND date = 2020-12-12 AND id > 50


            // Create a set of predicate builders, one for each column in the
            // order by list.
            $columnBuilders         = [];
            $previousColumnBuilders = [];
            foreach ($this->order as $column => $orderBy) {
                $value = $decodedCursor[$column] ?? null;
                if (!isset($value)) {
                    continue;
                }

                // Create a builder for finding all records lt/gt this value
                if ($orderBy === Mapper::SORT_ORDER_ASC) {
                    $thisBuilder = $builder->emptyClone()->greaterThan($column, $value);
                } elseif ($orderBy === Mapper::SORT_ORDER_DESC) {
                    $thisBuilder = $builder->emptyClone()->lessThan($column, $value);
                } else {
                    // This should be an error
                    continue;
                }

                // Piece together a builder for this order by column:
                // this builder is lt/gt this column, and also equals the value
                // of previous columns.
                // TODO consider an easier way to merge multiple builders together
                //  $builder->all(...$subBuilders) would be ideal
                $columnBuilder = $builder->emptyClone();
                foreach ($previousColumnBuilders as $previousColumnBuilder) {
                    $columnBuilder = $columnBuilder->all($previousColumnBuilder);
                }
                $columnBuilders[] = $columnBuilder->all($thisBuilder);

                // Create a builder which matches this column's value, so that
                // subsequent columns may find records which equal this, but are
                // lt/gt their value.
                $previousColumnBuilders[] = $builder->emptyClone()->equals($column, $value);
            }


            // Put each column builder into the main predicate builder
            $subBuilder = $builder->emptyClone();
            foreach ($columnBuilders as $columnBuilder) {
                // All the builders in the column builder must match
                $subBuilder = $subBuilder->all($columnBuilder);
            }
            // Any of the column builders may match
            $builder = $builder->any($subBuilder);
        }
        return $builder;
    }
}
