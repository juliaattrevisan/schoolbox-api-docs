<?php

/**
 * A join model between resources and their moderators.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\Moderator;

use Schoolbox\ResourceBooking\Item;
use Schoolbox\User;

class Model
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var User
     */
    protected $moderator;

    /**
     * @var Item\Model
     */
    protected $resource;

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  Item\Model $item
     * @return $this
     */
    public function setResource($item)
    {
        $this->resource = $item;
        return $this;
    }

    /**
     * @return Item\Model
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param  User $user
     * @return $this
     */
    public function setModerator($user)
    {
        $this->moderator = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getModerator()
    {
        return $this->moderator;
    }
}
