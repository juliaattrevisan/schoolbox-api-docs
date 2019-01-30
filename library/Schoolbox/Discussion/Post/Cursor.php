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
     * @return array
     */
    public function retrieveResults(?string $currentCursor): array
    {
        // Cursored result:
        // actual result set
        // metadata: current, next cursor
        $pb = $this->applyCursor(
            $this->mapper->getPredicateBuilder()
                         ->equals('discussion_thread_id', $this->thread->getId()),
            $currentCursor
        );

        $posts = $this->mapper->findByPredicates(
            $pb,
            ['limit' => self::RESULTS_PER_REQUEST, 'order' => ['id' => 'asc']]
        )->reindex();

        return [
            'data'     => $posts->map(new JsonSerializer()),
            'metadata' => [
                'cursor' => $this->buildCursor($posts, $currentCursor),
            ],
        ];
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
     * Builds the cursor to be output in the response metadata.
     *
     * @param GenericCollection $records
     * @param string|null       $currentCursor The current encoded cursor, if there is one.
     * @return array
     */
    private function buildCursor(GenericCollection $records, ?string $currentCursor): array
    {
        // Build current cursor, if required and possible
        if (!isset($currentCursor)) {
            $firstRecord = $records->first();
            if ($firstRecord && method_exists($firstRecord, 'getId')) {
                $currentCursor = $this->encodeCursor($firstRecord->getId() - 1);
            }
        }

        // Build next cursor
        $nextCursor = null;
        $lastRecord = $records->last();
        if ($lastRecord && method_exists($lastRecord, 'getId')) {
            $nextCursor = $this->encodeCursor($lastRecord->getId());
        }

        // And return
        return [
            'current' => $currentCursor,
            'next'    => $nextCursor,
        ];
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
