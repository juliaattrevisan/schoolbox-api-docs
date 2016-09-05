<?php
/**
 * IsOwner - Determine if a pastoral record is owned by the logged in user
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;

class IsOwner implements IAuthorizationRule
{
    /** @var PastoralMapper */
    protected $pastoralMapper;

    /**
     * IsOwner constructor.
     */
    public function __construct()
    {
        $this->setPastoralMapper(PastoralMapper::getInstance());
    }

    /**
     * Determine if the logged in user should have access to the requested pastoral care record
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $pastoralId = $params['id'];

        try {
            $record = $this->getPastoralMapper()->findById($pastoralId);

            if ($record->getOwner()->getId() == $user->getId()) {
                return true;
            }
        } catch (MapperException $e) {
            // Record didn't exist, creating new record- so they are the owner by default
            return true;
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }

    /** Here be getters and setters */

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper()
    {
        return $this->pastoralMapper;
    }

    /**
     * @param PastoralMapper $mapper
     * @return $this
     */
    public function setPastoralMapper(PastoralMapper $mapper)
    {
        $this->pastoralMapper = $mapper;
        return $this;
    }
}