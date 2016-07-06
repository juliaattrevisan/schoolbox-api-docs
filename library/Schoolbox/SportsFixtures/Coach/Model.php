<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Coach;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 * @package Schoolbox\SportsFixtures\Coach
 */
class Model implements \JsonSerializable
{
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
     * (If this model isn't saved yet, it returns the user's ID with a prefix.)
     *
     * @return string
     */
    public function formatId()
    {
        return $this->getId() ?: ('user:'.$this->getUser()->getId());
    }

    /**
     * Formats the name of this model.
     *
     * (If this model isn't saved yet, it returns the user's name.)
     *
     * @return string
     */
    public function formatName()
    {
        return $this->getId() ? $this->getName() : $this->getUser()->formatName();
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
        ];
    }
}
