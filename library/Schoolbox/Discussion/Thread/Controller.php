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
        $threads = $this->threadMapper->findForContext($contextType, $contextId);
        $this->response->content->set(
            $this->buildJsonOutput(
                $threads->map(new JsonSerializer())->reindex()
            )
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
            $this->buildJsonOutput(
                $posts->map(new Post\JsonSerializer())->reindex()
            )
        );
    }
}
