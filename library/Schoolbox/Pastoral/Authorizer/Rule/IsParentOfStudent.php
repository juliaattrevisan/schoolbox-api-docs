<?php
/**
 * IsParentOfStudent - Determine if user is parent of the student in the record we are viewing
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Authorizer\Failure\Unauthorized;

class IsParentOfStudent implements IAuthorizationRule
{
    /** @var UserMapper */
    protected $userMapper;

    /**
     * IsParentOfStudent constructor.
     */
    public function __construct()
    {
        $this->setUserMapper(UserMapper::getInstance());
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if (isset($params['studentId'])) {
            $student = $this->getUserMapper()->findById($params['studentId']);
        } else {
            $student = $this->getUserMapper()->findById($params['id']);
        }

        $student->loadParents();

        // Don't know WTF is going on here, but logging in as Jo Rhodes $user has all the parents details, but the ID is
        // that of the student?!?!?!

        $user = $this->getUserMapper()->findByExternalId($user->getExternalId());

        if ($user->isParentOf($student)) {
            return true;
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }

    /** Here be getters and setters */

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $mapper
     * @return $this
     */
    public function setUserMapper(UserMapper $mapper)
    {
        $this->userMapper = $mapper;
        return $this;
    }
}