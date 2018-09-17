<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use InvalidArgumentException;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\ValidArticleIsPubliclyReadable;
use Schoolbox\User;

/**
 * This determines whether a user can view an article's contents.
 * It uses the spec pattern ValidArticleIsPubliclyReadable to decide.
 */
class IsPubliclyReadable implements IAuthorizationRule
{

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var UserResolver
     */
    private $userResolver;

    /**
     * IsPubliclyReadable constructor.
     *
     * @param Mapper       $mapper
     * @param UserResolver $userResolver
     */
    public function __construct(Mapper $mapper, UserResolver $userResolver)
    {
        $this->mapper = $mapper;
        $this->userResolver = $userResolver;
    }


    /**
     * Performs an authorization check.
     *
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws InvalidArgumentException
     * @throws Unauthorized
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function authorize(array $params = [], User $user = null): bool
    {
        if (!$user) {
            throw new InvalidArgumentException('No session user available to test permissions');
        }

        $article = $this->mapper->findById($params['articleId']);

        if (!(new ValidArticleIsPubliclyReadable($this->userResolver))->isSatisfiedBy($user, $article)) {
            throw new Unauthorized('This user cannot read article contents');
        }

        return true;
    }
}
