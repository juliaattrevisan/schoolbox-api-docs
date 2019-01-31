<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Application\Exception\NotFound;

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
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
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
     * @api   POST /posts
     * @level 190
     */
    public function createPost(): void
    {
        // build object

        // post data should be in content, not form data
        $data   = $this->request->content->get();

        // This shouldn't be a string, but the above says it is?
        $thread = $this->threadMapper->findById($data['thread']['id'] ?? null);

        $post = $this->mapper->createModel()
                             ->setThread($this->threadMapper->findById($data));
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
