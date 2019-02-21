<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use Schoolbox\Discussion\Thread;
use Schoolbox\GenericCollection;

/**
 * Class Cursor
 */
class Cursor
{
    /**
     * The number of posts to return per request.
     */
    private const RESULTS_PER_REQUEST = 10;


    /**
     * The order in which to retrieve results (ascending or descending)
     */
    public const ORDER_ASC = 'asc';
    public const ORDER_DESC = 'desc';


    /**
     * The mapper class that this cursor is being built for.
     *
     * @var Mapper
     */
    private $mapper;


    /**
     * The thread for which to retrieve posts.
     *
     * @var Thread\Model
     */
    private $thread;


    /**
     * The order in which to retrieve posts.
     *
     * @var string
     */
    private $order;


    /**
     * Constructor
     *
     * @param Mapper       $mapper
     * @param Thread\Model $thread
     * @param string       $order
     * @throws InvalidArgumentException if $order isn't one of this class' ORDER_* constants
     */
    public function __construct(Mapper $mapper, Thread\Model $thread, string $order = self::ORDER_ASC)
    {
        $this->mapper = $mapper;
        $this->thread = $thread;

        if (!in_array($order, [self::ORDER_ASC, self::ORDER_DESC], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Order must be one of the %s::ORDER_* constants',
                    get_class($this)
                )
            );
        }
        $this->order = $order;
    }


    /**
     * Retrieve the page of results that $currentCursor points to.
     *
     * @param string|null $currentCursor The current encoded cursor, if there is one.
     * @return GenericCollection
     */
    public function retrieveResults(?string $currentCursor): GenericCollection
    {
        $pb       = $this->mapper->getPredicateBuilder();
        $threadId = $this->thread->getId();
        try {
            /** @noinspection PhpUnhandledExceptionInspection */
            $pb = $pb->equals('discussion_thread_id', $threadId);
        } catch (\TypeError $e) {
            // $thread doesn't exist yet
            /** @noinspection PhpUnhandledExceptionInspection */
            $pb = $pb->isNull('discussion_thread_id');
        }
        $pb = $this->applyCursor($pb, $currentCursor);

        return $this->mapper->findByPredicates(
            $pb,
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
    public function retrieveCurrentCursor(GenericCollection $collection): ?string
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
            if ($this->order === self::ORDER_ASC) {
                $previousPageCursorId--;
            } elseif ($this->order === self::ORDER_DESC) {
                $previousPageCursorId++;
            }
            $currentCursor = $this->encodeCursor($previousPageCursorId);
        }
        return $currentCursor;
    }


    /**
     * Retrieves a cursor to the next collection.
     *
     * @param GenericCollection $collection
     * @return string|null
     */
    public function retrieveNextCursor(GenericCollection $collection): ?string
    {
        $nextCursor  = null;
        $lastRecord  = $collection->last();
        $getIdMethod = $this->mapper->getGetIdentifierMethodName();

        // The next cursor points to the last record.
        if ($lastRecord && method_exists($lastRecord, $getIdMethod)) {
            $nextCursor = $this->encodeCursor($lastRecord->$getIdMethod());
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
        if (isset($cursor)) {
            $lastId = $this->decodeCursor($cursor);
            if (isset($lastId) && $lastId > 0) {
                if ($this->order === self::ORDER_ASC) {
                    // Ascending: get only items greater than last ID
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $builder->greaterThan($this->mapper->getIdentifierColumn(), $lastId);
                } elseif ($this->order === self::ORDER_DESC) {
                    // Descending: get only items less than last ID
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $builder->lessThan($this->mapper->getIdentifierColumn(), $lastId);
                }
            }
        }
        return $builder;
    }


    /**
     * Encode a cursor in a form which is passable in a query string.
     *
     * @param int|null $id
     * @return string|null
     */
    private function encodeCursor(?int $id): ?string
    {
        return isset($id) ? base64_encode((string)$id) : null;
    }


    /**
     * Decode a cursor that was passed in a query string.
     *
     * @param string|null $cursor
     * @return int|null
     */
    private function decodeCursor(?string $cursor): ?int
    {
        return filter_var(base64_decode($cursor), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

}
