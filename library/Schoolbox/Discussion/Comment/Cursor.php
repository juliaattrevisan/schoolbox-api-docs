<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Codec\ICodec;
use Alaress\Codec\Integer\Base64;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Discussion\Thread;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Class Cursor
 */
class Cursor implements ICursor
{
    /**
     * The number of comments to return per request.
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
     * The thread for which to retrieve comments.
     *
     * @var Thread\Model
     */
    private $thread;


    /**
     * The order in which to retrieve comments.
     *
     * @var string
     */
    private $order;


    /**
     * How many results (max) should
     */
    private int $limit;


    /**
     * The codec implementation used for the cursor.
     *
     * @var ICodec
     */
    private $codec;


    /**
     * Constructor
     *
     * @param Mapper       $mapper
     * @param Thread\Model $thread
     * @param string       $order
     * @param int          $limit
     * @throws InvalidArgumentException if $order isn't one of this class' ORDER_* constants
     */
    public function __construct(
        Mapper $mapper,
        Thread\Model $thread,
        string $order = self::ORDER_ASC,
        int $limit = self::RESULTS_PER_REQUEST
    ) {
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
        $this->limit = $limit;
        $this->codec = new Base64();
    }


    /**
     * Retrieve the total number of results that are in the thread, independent
     * of any cursor.
     *
     * @return int
     * @throws MapperException if the query fails
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
     * @throws MapperException if the query fails
     */
    public function retrieveResults(?string $currentCursor): GenericCollection
    {
        return $this->mapper->findByPredicates(
            $this->applyCursor($this->buildPredicateBuilder(), $currentCursor),
            [
                'limit' => $this->limit,
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
     * @param Collection $collection
     * @return string|null
     */
    public function current(Collection $collection): ?string
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
            $currentCursor = $this->codec->encode($previousPageCursorId);
        }
        return $currentCursor;
    }


    /**
     * Retrieves a cursor to the next collection.
     *
     * @param Collection $collection
     * @return string|null
     */
    public function next(Collection $collection): ?string
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
     * Create a predicate builder for retrieving the list of comments.
     *
     * @return Builder
     */
    private function buildPredicateBuilder(): Builder
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
        return $pb;
    }
}
