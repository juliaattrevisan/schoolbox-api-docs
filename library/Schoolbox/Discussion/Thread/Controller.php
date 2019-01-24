<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Database\Predicate\Builder;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Discussion\Post;
use Schoolbox\GenericCollection;

/**
 * Controller for thread-related API requests.
 */
class Controller extends BaseController
{
    /**
     * The number of threads to return per request.
     */
    private const THREAD_RESULTS_PER_REQUEST = 10;


    /**
     * The number of posts to return per request.
     */
    private const POST_RESULTS_PER_REQUEST = 100;


    /**
     * @var Mapper
     */
    private $threadMapper;


    /**
     * @var Post\Mapper
     */
    private $postMapper;


    /**
     * Constructor
     *
     * @param Mapper      $threadMapper
     * @param Post\Mapper $postMapper
     */
    public function __construct(Mapper $threadMapper, Post\Mapper $postMapper)
    {
        $this->threadMapper = $threadMapper;
        $this->postMapper   = $postMapper;
    }


    /**
     * Get a list of threads by their context.
     *
     * @api   GET /threads/context/{contextType}/id/{contextId}
     * @level 190
     *
     * @param string $contextType
     * @param int    $contextId
     */
    public function getThreadsByContext(string $contextType, int $contextId): void
    {
        // Cursoring:
        // "get x records, starting after last record y"
        $pb = $this->applyCursor(
            $this->threadMapper->getPredicateBuilder()
                               ->equals('c.context_type', $contextType)
                               ->equals('c.context_id', $contextId),
            $this->request->query->get('cursor')
        );

        // Fetch results
        $threads = $this->threadMapper->findByPredicates(
            $pb,
            ['limit' => self::THREAD_RESULTS_PER_REQUEST, 'order' => ['id' => 'asc']]
        )->reindex();

        // Output results
        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $threads->map(new JsonSerializer()),
                'metadata' => [
                    'cursor' => $this->buildCursor($threads),
                ],
            ])
        );
    }


    /**
     * Get a list of posts in the given thread.
     *
     * @api   GET /threads/{id}/posts
     * @level 190
     *
     * @param Model $thread
     */
    public function getPostsByThread(Model $thread): void
    {
        // Build cursor predicate builder
        $pb = $this->applyCursor(
            $this->postMapper->getPredicateBuilder()
                             ->equals('discussion_thread_id', $thread->getId()),
            $this->request->query->get('cursor')
        );

        // Fetch results
        $posts = $this->postMapper->findByPredicates(
            $pb,
            ['limit' => self::POST_RESULTS_PER_REQUEST, 'order' => ['id' => 'asc']]
        )->reindex();

        // Output results
        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $posts->map(new Post\JsonSerializer()),
                'metadata' => [
                    'cursor' => $this->buildCursor($posts),
                ],
            ])
        );
    }


    /**
     * Apply a cursor to the predicate builder.
     *
     * TODO look at extracting cursoring to its own class
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
     * @return array
     */
    private function buildCursor(GenericCollection $records): array
    {
        $nextCursor = null;
        $lastRecord = $records->last();
        if ($lastRecord && method_exists($lastRecord, 'getId')) {
            $nextCursor = $this->encodeCursor($lastRecord->getId());
        }
        // It's currently too awkward to generate a previous cursor.
        // You don't need it, you already have the results from the last cursor!
        return [
            'next' => $nextCursor,
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
