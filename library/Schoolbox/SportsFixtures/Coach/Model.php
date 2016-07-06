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
    use ModelTrait\ModifiedAtDate;
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
     * Serialize
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
            'user' => $this->getUser(),
        ];
    }
}
