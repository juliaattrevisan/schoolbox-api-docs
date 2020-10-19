<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Codec\ICodec;
use Alaress\Codec\Integer\Base64;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Cursor for learning moments.
 */
class Cursor
{
    /**
     * The number of comments to return per request.
     */
    private const RESULTS_PER_REQUEST = 10;


    /**
     * The mapper class that this cursor is being built for.
     *
     * @var Mapper
     */
    private Mapper $mapper;


    /**
     * The user for which to retrieve evidence.
     *
     * @var User
     */
    private User $user;


    /**
     * The order in which to retrieve users.
     *
     * @var string
     */
    private $order;


    /**
     * The codec implementation used for the cursor.
     *
     * @var ICodec
     */
    private $codec;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     * @param User   $user
     * @param string $order
     * @throws InvalidArgumentException if $order isn't one of this class' ORDER_* constants
     */
    public function __construct(Mapper $mapper, User $user, string $order = Mapper::SORT_ORDER_ASC)
    {
        $this->mapper = $mapper;
        $this->user   = $user;

        if (!in_array($order, [Mapper::SORT_ORDER_ASC, Mapper::SORT_ORDER_DESC], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Order must be one of the %s::SORT_ORDER_* constants',
                    Mapper::class
                )
            );
        }
        $this->order = $order;
        $this->codec = new Base64();
    }


    /**
     * Retrieve the total number of learning moments for this user, independent
     * of any cursor.
     *
     * @return int
     */
    public function retrieveResultCount(): int
    {
        return $this->mapper->countByPredicates($this->buildPredicateBuilder());
    }


    /**
     * Retrieve the page of results that $currentCursor points to.
     *
     * @param string|null $currentCursor The current encoded cursor, if there is one.
     * @return GenericCollection
     */
    public function retrieveResults(?string $currentCursor): GenericCollection
    {
        return $this->mapper->findByPredicates(
            $this->applyCursor($this->buildPredicateBuilder(), $currentCursor),
            [
                'limit' => self::RESULTS_PER_REQUEST,
                'order' => [
                    $this->mapper->getIdentifierColumn() => $this->order,
                ],
            ]
        )->reindex();
    }


    /**
     * Retrieve a cursor to the current collection.
     *
     * This may be useful when retrieving a result set without a cursor, in
     * case the initial result set at this time needs to be fetched.
     *
     * @param GenericCollection $collection
     * @return string|null
     */
    public function current(GenericCollection $collection): ?string
    {
        $currentCursor = null;
        $firstRecord   = $collection->first();
        $getIdMethod   = $this->mapper->getGetIdentifierMethodName();

        // The current cursor points to one record before the first item in
        // the record set.
        // In other words, the next cursor for the previous record set.
        // Ascending:  id - 1
        // Descending: id + 1
        if ($firstRecord && method_exists($firstRecord, $getIdMethod)) {
            $previousPageCursorId = $firstRecord->$getIdMethod();
            if ($this->order === Mapper::SORT_ORDER_ASC) {
                $previousPageCursorId--;
            } elseif ($this->order === Mapper::SORT_ORDER_DESC) {
                $previousPageCursorId++;
            }
            $currentCursor = $this->codec->encode($previousPageCursorId);
        }
        return $currentCursor;
    }


    /**
     * Retrieves a cursor to the next collection.
     *
     * @param GenericCollection $collection
     * @return string|null
     */
    public function next(GenericCollection $collection): ?string
    {
        $nextCursor  = null;
        $lastRecord  = $collection->last();
        $getIdMethod = $this->mapper->getGetIdentifierMethodName();

        // The next cursor points to the last record.
        if ($lastRecord && method_exists($lastRecord, $getIdMethod)) {
            $nextCursor = $this->codec->encode($lastRecord->$getIdMethod());
        }
        return $nextCursor;

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
                } elseif ($this->order === Mapper::SORT_ORDER_DESC) {
                    // Descending: get only items less than last ID
                    $builder->lessThan($this->mapper->getIdentifierColumn(), $lastId);
                }
            }
        }
        return $builder;
    }


    /**
     * Create a predicate builder for retrieving the learning moments.
     *
     * @return Builder
     */
    private function buildPredicateBuilder(): Builder
    {
        return $this->mapper->getPredicateBuilder()
                            ->equals('user_id', $this->user->getId());
    }

}
