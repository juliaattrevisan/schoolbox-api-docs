<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Json\Decorator;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Discussion\Post;
use Schoolbox\Json\Decorator\LinkDecorator;

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
        $cursor        = new Post\Cursor($this->postMapper, $thread, Post\Cursor::ORDER_DESC);
        $encodedCursor = $this->request->query->get('cursor');
        $posts         = $cursor->retrieveResults($encodedCursor);


        // I need to:
        // * Output dates as RFC3339 format
        $linkDecorator = new LinkDecorator($this->router, $this->authorizer, $this->getUser());
        // The link decorator is required as an extra decorator:
        // links need to be added to the author within the post
        $jsonDecorator = new Decorator\PropertyDecorator(
            null,
            $linkDecorator
        );


        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $posts->jsonSerialize($jsonDecorator, $linkDecorator),
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
