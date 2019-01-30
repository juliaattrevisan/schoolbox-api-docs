<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Database\Predicate\Builder;
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
    private const RESULTS_PER_REQUEST = 100;


    /**
     * The direction of results (ascending or descending)
     */
    public const DIRECTION_ASC = 'asc';
    public const DIRECTION_DESC = 'desc';


    /**
     * The mapper class that this cursor is being built for.
     *
     * @var Mapper
     */
    private $mapper;


    /**
     * @var Thread\Model
     */
    private $thread;


    /**
     * Constructor
     *
     * @param Mapper       $mapper
     * @param Thread\Model $thread
     */
    public function __construct(Mapper $mapper, Thread\Model $thread)
    {
        $this->mapper = $mapper;
        $this->thread = $thread;
    }


    /**
     * Retrieve the page of results that $currentCursor points to.
     *
     * @param string|null $currentCursor The current encoded cursor, if there is one.
     * @return GenericCollection
     */
    public function retrieveResults(?string $currentCursor): GenericCollection
    {
        $pb = $this->applyCursor(
            $this->mapper->getPredicateBuilder()
                         ->equals('discussion_thread_id', $this->thread->getId()),
            $currentCursor
        );

        return $this->mapper->findByPredicates(
            $pb,
            ['limit' => self::RESULTS_PER_REQUEST, 'order' => ['id' => 'asc']]
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
        if ($firstRecord && method_exists($firstRecord, $getIdMethod)) {
            $currentCursor = $this->encodeCursor($firstRecord->$getIdMethod() - 1);
        }

        // And return
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
        if ($lastRecord && method_exists($lastRecord, $getIdMethod)) {
            $nextCursor = $this->encodeCursor($lastRecord->$getIdMethod());
        }

        // And return
        return $nextCursor;

    }


    /**
     * Apply a cursor to the predicate builder.
     *
     * @param Builder     $builder
     * @param string|null $cursor
     * @return Builder
     */
    private function applyCursor(Builder $builder, ?string $cursor): Builder
    {
        if (isset($cursor)) {
            $lastId = $this->decodeCursor($cursor);
            if (isset($lastId) && $lastId > 0) {
                // Which direction? Assume always ascending (oldest first) for now
                $builder->greaterThan('id', $lastId);
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
