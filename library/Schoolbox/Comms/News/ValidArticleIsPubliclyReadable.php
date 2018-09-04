<?php
/**
 * ValidArticleIsPubliclyReadable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\User;

class ValidArticleIsPubliclyReadable
{
    /**
     * @var UserResolver
     */
    private $userResolver;

    /**
     * ValidArticleIsPubliclyReadable constructor.
     * @param UserResolver $userResolver
     */
    public function __construct(UserResolver $userResolver)
    {
        $this->userResolver = $userResolver;
    }


    /**
     * This prevents articles in draft/moderation phases from being seen.
     *
     * If an article is published or expired and in the archive, and you're in its audience, you can
     * see its contents.
     *
     * If you aren't in the audience and the article is shareable,
     * then you can see its contents.
     *
     * Otherwise, you can't see it.
     *
     * @param User       $user
     * @param Model      $article
     * @param array|null $audiencedUsers
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, Model $article, array $audiencedUsers = null): bool
    {
        $isPublished = $article->isPublished();
        $isExpired = $article->isExpired();
        $isShareable = $article->isShareable();

        /* Only published/expired articles can be publicly seen
         */
        if ($isPublished || $isExpired) {

            /* If you are in its audience, you can see it
             * Use the list specified in the signature, or determine this list if otherwise omitted
             */
            if (!\is_array($audiencedUsers)) {
                $audiencedUsers = $this->userResolver->findUsersForArticle($article->getId());
            }

            if (\in_array($user->getId(), $audiencedUsers, false)) {
                return true;
            }

            /* If it's shareable, you can see it
             */
            if ($isShareable) {
                return true;
            }
        }

        return false;
    }
}
