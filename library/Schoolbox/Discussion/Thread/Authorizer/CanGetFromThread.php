<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Authorizer as BaseAuthorizer;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks where the thread is in use, and verifies whether the
 * requesting user has permission to view that place.
 */
class CanGetFromThread extends CanActUponThread
{
    /**
     * @var BaseAuthorizer
     */
    private $baseAuthorizer;


    /**
     * Constructor
     *
     * @param BaseAuthorizer $baseAuthorizer
     * @param News\Mapper    $articleMapper
     */
    public function __construct(BaseAuthorizer $baseAuthorizer, News\Mapper $articleMapper)
    {
        parent::__construct($articleMapper);
        $this->baseAuthorizer = $baseAuthorizer;
    }


    /**
     * If I am the author of a post, I may view it.
     *
     * @param Post\Model $post
     * @param User|null  $user
     * @return bool
     */
    protected function validateAgainstPost(Post\Model $post, ?User $user): bool
    {
        $author = $post->getAuthor();
        return $user && $author && $user->getId() === $author->getId();
    }


    /**
     * If I can view a news article, I can view its comments.
     *
     * @param  News\Model $article
     * @param  User|null  $user
     * @return bool
     */
    protected function validateAgainstCommsArticle(News\Model $article, ?User $user): bool
    {
        return $this->baseAuthorizer->isPathAuthorizedForUser(
            'commsNews.getDetail',
            ['articleId' => $article->getId(), 'article' => $article],
            $user
        );
    }
}
