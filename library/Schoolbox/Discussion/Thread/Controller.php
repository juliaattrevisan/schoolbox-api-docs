<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Discussion\Post;

/**
 * Controller for thread-related API requests.
 */
class Controller extends BaseController
{
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
        // First, check there is a cursor:
        $pb = $this->threadMapper->getPredicateBuilder()
            ->equals('c.context_type', $contextType)
            ->equals('c.context_id', $contextId);

        $cursor = $this->request->query->get('cursor');
        if (isset($cursor)) {
            $lastId = filter_var(base64_decode($cursor), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            if (isset($lastId) && $lastId > 0) {
                // Which direction? Assume always ascending (oldest first) for now
                $pb->greaterThan('id', $lastId);
            }
        }


        // Now actually get them
        $threads = $this->threadMapper->findByPredicates(
            $pb,
            ['limit' => 10, 'order' => ['id' => 'desc']]
        )->reindex();
        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $threads->map(new JsonSerializer()),
                'metadata' => [
                    'cursor' => [
                        'next' => $threads->last() ? base64_encode($threads->last()->getId()) : null
                    ],
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
        $posts = $this->postMapper->findByColumn('discussion_thread_id', $thread->getId());
        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $posts->map(new Post\JsonSerializer())->reindex(),
                'metadata' => [],
            ])
        );
    }
}
