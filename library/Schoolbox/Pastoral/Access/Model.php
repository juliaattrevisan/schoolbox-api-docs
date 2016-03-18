<?php
/**
 * Pastoral care access control
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Access;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $pastoralId;

    /** @var \Schoolbox\Role */
    protected $role;

    /** @var \Schoolbox\User */
    protected $user;

    /** @var \Alaress_Date_Format */
    protected $datetimeDeleted;

    /**
     * @param string $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
        return $pastoralId;
    }

    /**
     * @return int
     */
    public function getPastoralId() {
        return $this->pastoralId;
    }

    /**
     * @param \Schoolbox\Role $role
     * @return self
     */
    public function setRole($role) {
        $this->role = $role;
        return $role;
    }

    /**
     * @return \Schoolbox\Role
     */
    public function getRole() {
        return $this->role;
    }

    /**
     * @param \Schoolbox\User $user
     * @return self
     */
    public function setUser($user) {
        $this->user = $user;
        return $user;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @param int $id
     * @return self
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
     * @param \Alaress_Date_Format $datetimeDeleted
     * @return self
     */
    public function setDatetimeDeleted($datetimeDeleted) {
        $this->datetimeDeleted = $datetimeDeleted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimeDeleted() {
        return $this->datetimeDeleted;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return !$this->getDatetimeDeleted() instanceof \Alaress_Date_NullDate;
    }
}
