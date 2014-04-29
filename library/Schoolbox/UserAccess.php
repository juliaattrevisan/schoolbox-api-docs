<?php
/**
 * User access for Schoolbox.
 *
 * Currently just a namespace, but may be fleshed out to a whole class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox;

class UserAccess {

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for homepage $this->itemId.
     */
    const SERVICE_HOMEPAGE  = 1;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    const SERVICE_CALENDAR = 2;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    const SERVICE_NEWS = 3;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for tutorials for campus
     * $this->itemId.
     *
     * @todo check if this is actually used
     */
    const SERVICE_TUTORIAL = 4;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for all homepages.
     *
     * @todo confirm if this is the case
     */
    const SERVICE_CMS = 5;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the leave management
     * system.
     */
    const SERVICE_HOUSEMASTER = 6;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the resource booking
     * system.
     */
    const SERVICE_RESOURCE_BOOKING = 7;

    /**
     * Value for $this->accessLevel:
     * $this->user is denied access to resource in $this->service.
     */
    const ACCESS_DENY  = 0;

    /**
     * Value for $this->accessLevel:
     * $this->user has read access to resource in $this->service.
     */
    const ACCESS_READ  = 1;

    /**
     * Value for $this->accessLevel:
     * $this->user has write access to resource in $this->service.
     */
    const ACCESS_WRITE = 2;

    /**
     * Value for $this->accessLevel:
     * $this->user has moderate access to resource in $this->service.
     */
    const ACCESS_FULL  = 3;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var int
     */
    protected $service;

    /**
     * @var int
     */
    protected $itemId;

    /**
     * @var int
     */
    protected $accessLevel;

    /**
     * @param  mixed $accessLevel
     * @return $this
     */
    public function setAccessLevel($accessLevel) {
        $this->accessLevel = $accessLevel;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAccessLevel() {
        return $this->accessLevel;
    }

    /**
     * @param  int   $itemId
     * @return $this
     */
    public function setItemId($itemId) {
        $this->itemId = $itemId;
        return $this;
    }

    /**
     * @return int
     */
    public function getItemId() {
        return $this->itemId;
    }

    /**
     * @param  int   $service
     * @return $this
     */
    public function setService($service) {
        $this->service = $service;
        return $this;
    }

    /**
     * @return int
     */
    public function getService() {
        return $this->service;
    }

    /**
     * @param  User  $user
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
