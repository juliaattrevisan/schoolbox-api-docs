<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Authorizer as BaseAuthorizer;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;
use Schoolbox\Discussion\Thread;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks where the thread is in use, and verifies whether the
 * requesting user has permission to act upon that place.
 */
abstract class CanActUponThread implements IAuthorizationRule
{
    /**
     * @var News\Mapper
     */
    private $articleMapper;


    /**
     * Constructor
     *
     * @param News\Mapper $articleMapper
     */
    public function __construct(News\Mapper $articleMapper)
    {
        $this->articleMapper  = $articleMapper;
    }


    /**
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): bool
    {
        // Extract thread from params: first "thread", then out of "post"
        $thread = $params['thread'] ?? null;
        if (!$thread instanceof Thread\Model) {
            $post = $params['post'] ?? null;
            if (!$post instanceof Post\Model) {
                throw new \InvalidArgumentException('Could not find thread in either "thread" or "post" param');
            }
            $thread = $post->getThread();
        }

        // Search for the thread in all the places it could be.
        // Right now: that's only comms.
        // (A thread should only be used in one place, but it's possible to
        //  check multiples.)
        $articles = $this->articleMapper->findByColumn('discussion_thread_id', $thread->getId());
        foreach ($articles as $article) {
            if ($this->validateAgainstCommsArticle($article, $user)) {
                return true;
            }
        }

        throw new Failure\Unauthorized();
    }


    /**
     * Is this thread action allowed for threads that are associated with this
     * article?
     *
     * @param  News\Model $article
     * @param  User|null  $user
     * @return bool
     */
    abstract protected function validateAgainstCommsArticle(News\Model $article, ?User $user): bool;
}
