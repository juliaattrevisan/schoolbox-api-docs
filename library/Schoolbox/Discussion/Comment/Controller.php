<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Web\Controller\Base as BaseController;
use Aura\Web\Exception\InvalidStatusCode;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for comment-related API requests.
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
     * Get a list of comments with the given parent comment.
     *
     * TODO implement this endpoint
     *
     * @api   GET /discussion/{contextType}/{contextId}/comments/{id}/comments
     * @since 190
     *
     * @param Model $comment
     * @throws NotFound This is not yet implemented
     */
    public function getCommentsByParent(Model $comment): void
    {
        // 404 for now I guess?
        throw new NotFound();
    }


    /**
     * Delete a comment.
     *
     * @api   DELETE /discussion/{contextType}/{contextId}/comments/{id}
     * @since 190
     *
     * @param Model $comment
     * @throws MapperException
     * @throws InvalidStatusCode if somehow 204 becomes an invalid status code?!
     */
    public function deleteComment(Model $comment): void
    {
        if (!$comment->isDeleted()) {
            $this->mapper->delete($comment);
        }
        $this->response->status->setCode(204);
    }
}
