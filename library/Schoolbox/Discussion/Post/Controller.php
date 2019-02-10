<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Thread;

/**
 * Controller for post-related API requests.
 */
class Controller extends BaseController
{
    /**
     * @var Mapper
     */
    private $mapper;


    /**
     * @var Thread\Mapper
     */
    private $threadMapper;


    /**
     * Constructor
     *
     * @param Mapper        $mapper
     * @param Thread\Mapper $threadMapper
     */
    public function __construct(Mapper $mapper, Thread\Mapper $threadMapper)
    {
        $this->mapper       = $mapper;
        $this->threadMapper = $threadMapper;
    }


    /**
     * Get a list of posts with the given parent post.
     *
     * @api   GET /posts/{id}/posts
     * @level 190
     *
     * @param Model $post
     */
    public function getPostsByParent(Model $post): void
    {
        // 404 for now I guess?
        throw new NotFound();
    }


    /**
     * Create a post.
     *
     * @todo consider POST /threads/{threadId}/posts/ instead
     *
     * @api   POST /posts
     * @level 190
     */
    public function createPost(): void
    {
        // build object

        // post data should be in application/json, not form data
        $data = json_decode($this->request->content->get());

        // This shouldn't be a string, but the above says it is?
        $thread = $this->threadMapper->findById($data['thread']['id'] ?? null);

        $post = $this->mapper->createModel()
            ->setThread($thread)
            ->setAuthor($this->getUser())
            ->setContent($data['thread']['content']);

        $this->mapper->save($post);

        // Return something
    }


    /**
     * Delete a post.
     *
     * @api   DELETE /posts/{id}
     * @level 190
     *
     * @param Model $post
     */
    public function deletePost(Model $post): void
    {
        // 404 for now I guess?
        throw new NotFound();
//        $this->mapper->delete($post);
//        $this->response->status->setCode(204);
    }
}
