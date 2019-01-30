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
     * @var Post\Mapper
     */
    private $postMapper;


    /**
     * Constructor
     *
     * @param Post\Mapper $postMapper
     */
    public function __construct(Post\Mapper $postMapper)
    {
        $this->postMapper = $postMapper;
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
        $cursor        = new Post\Cursor($this->postMapper, $thread);
        $encodedCursor = $this->request->query->get('cursor');
        $posts         = $cursor->retrieveResults($encodedCursor);

        $this->response->content->set(
            $this->buildJsonOutput([
                'data' => $posts->map(new Post\JsonSerializer()),
                'metadata' => [
                    'cursor' => [
                        'current' => $encodedCursor ?? $cursor->retrieveCurrentCursor($posts),
                        'next'    => $cursor->retrieveNextCursor($posts),
                    ]
                ]
            ])
        );
    }
}
