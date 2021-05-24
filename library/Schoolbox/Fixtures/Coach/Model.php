<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Coach;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 * @package Schoolbox\Fixtures\Coach
 */
class Model implements \JsonSerializable, ISoftDeletable
{
    const PREFIX_EXISTING_TAG   = 'existing';
    const PREFIX_NEW_FROM_USER  = 'newFromUser';
    const PREFIX_NEW_FROM_INPUT = 'newFromInput';

    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * The name of this coach.
     *
     * @var string
     */
    protected $name;

    /**
     * The user account (if any!) that this coach is associated with.
     *
     * @var User
     */
    protected $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user = new User();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Formats the ID of this model.
     *
     * It applies the appropriate prefix, depending on whether:
     * * the coach is already saved
     * * the coach doesn't exist yet but is based off an existing user
     * * the coach doesn't exist yet and doesn't exist as a user
     *
     * @return string
     */
    public function formatId()
    {
        if ($this->getId()) {
            return self::PREFIX_EXISTING_TAG . ':' . $this->getId();
        } elseif ($this->getUser()->getId()) {
            return self::PREFIX_NEW_FROM_USER . ':' . $this->getUser()->getId();
        } else {
            return self::PREFIX_NEW_FROM_INPUT . ':' . $this->getName();
        }
    }

    /**
     * Formats the name of this model.
     *
     * @return string
     */
    public function formatName()
    {
        if ($this->getUser()->getId()) {
            // Format similarly to ajax user
            $name = sprintf("%s - %s", $this->getUser()->formatName(), $this->getUser()->getRole()->getName());
            if ($this->getUser()->getExternalId()) {
                $name .= ' (' . $this->getUser()->getExternalId() . ')';
            }
            return $name;
        } else {
            return $this->getName();
        }
    }

    /**
     * Serialize
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id'   => $this->formatId(),
            'name' => $this->formatName(),
            'user' => $this->getUser(),
        ];
    }
}
