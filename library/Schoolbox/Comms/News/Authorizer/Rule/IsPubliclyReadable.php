<?php
/**
 * IsPubliclyReadable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use InvalidArgumentException;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\ValidArticleIsPubliclyReadable;
use Schoolbox\User;

class IsPubliclyReadable implements IAuthorizationRule
{

    /**
     * @var Mapper
     */
    private $mapper;


    /**
     * CanModify constructor.
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * Performs an authorization check.
     *
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws InvalidArgumentException
     * @throws Unauthorized
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): bool
    {
        if (!$user) {
            throw new InvalidArgumentException('No session user available to test permissions');
        }

        $article = $this->mapper->findById($params['articleId']);

        if (!(new ValidArticleIsPubliclyReadable())->isSatisfiedBy($user, $article)) {
            throw new Unauthorized('This user cannot read article contents');
        }

        return true;
    }
}
