<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Comms\News\Audience\IUserResolver;
use Schoolbox\User;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Checks whether an user is in an article's audience
 */
class IsMemberOfArticleAudience extends ArticleAttribute
{

    /** @var IUserResolver */
    private $userResolver;


    /**
     * @param IUserResolver $userResolver
     */
    public function __construct(IUserResolver $userResolver)
    {
        $this->userResolver = $userResolver;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @throws InvalidArgumentException
     * @throws Unauthorized
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new InvalidArgumentException('$user must not be null');
        }
        $article = $this->findArticleInParams($params);

        $usersInAudience = $this->userResolver->findUsersForArticle($article->getId());

        if (!\in_array($user->getId(), $usersInAudience, false)) {
            throw new Unauthorized("User {$user->getId()} is not in the audience of article {$article->getId()}");
        }
    }
}
