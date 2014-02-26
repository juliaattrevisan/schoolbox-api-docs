<?php
/**
 * A join model between resources and their moderators.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\Moderator;

use Schoolbox\ResourceBooking\Item;
use Schoolbox\User;

class Model {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Item\Model
     */
    protected $item;

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param  Item\Model $item
     * @return $this
     */
    public function setItem($item) {
        $this->item = $item;
        return $this;
    }

    /**
     * @return Item\Model
     */
    public function getItem() {
        return $this->item;
    }

    /**
     * @param  User $user
     * @return $this
     */
    public function setUser($user) {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser() {
        return $this->user;
    }


}
