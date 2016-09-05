<?php
/**
 * IsInAudience - Determine if a pastoral record should be viewable by the logged in user based on audienced roles/users
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;

class IsInAudience implements IAuthorizationRule
{
    /** @var PastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralAccessMapper */
    protected $pastoralAccessMapper;

    /**
     * IsInAudience constructor.
     */
    public function __construct()
    {
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
    }

    /**
     * Determine if the logged in user should have access to the requested pastoral care record
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $pastoralId = $params['id'];
        $record = $this->getPastoralMapper()->findById($pastoralId);
        $role = $user->getRole();

        // Give owners permission to their own records regardless of other permissions

        if ($record->getOwner()->getId() == $user->getId()) {
            return true;
        }

        // Check if the record is private make sure the user is allowed access

        if ($record->getPrivate()) {
            if (!$this->getPastoralMapper()->hasPrivatePermission($record)) {
                throw new Unauthorized();
            }
        }

        // If we get through to here we need to check the audience of item

        $accessRecords = $this->getPastoralAccessMapper()->findByColumn("pastoral_id", $pastoralId);

        /** @var PastoralAccessModel $accessCurrent */
        foreach ($accessRecords as $accessCurrent) {
            // Access records are either role based of user based.

            if (is_numeric($accessCurrent->getRole()->getId())) {
                // This access record is a role one, check if the user is in this role

                if ($role->getId() == $accessCurrent->getRole()->getId()) {
                    return true;
                }
            } else {
                // This access record is a user based one, check if this is the user

                if ($accessCurrent->getUser()->getId() == $user->getId()) {
                    return true;
                }
            }
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }

    /** Here be getters and setters */

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper()
    {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param PastoralAccessMapper $mapper
     * @return $this
     */
    public function setPastoralAccessMapper(PastoralAccessMapper $mapper)
    {
        $this->pastoralAccessMapper = $mapper;
        return $this;
    }

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