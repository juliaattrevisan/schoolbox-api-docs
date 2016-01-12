<?php
/**
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

    /** @var int */
    protected $roleId;

    /** @var int */
    protected $userId;

    /** @var \Alaress_Date_Format */
    protected $deletedDate;

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
     * @param string $roleId
     * @return self
     */
    public function setRoleId($roleId) {
        $this->roleId = $roleId;
        return $roleId;
    }

    /**
     * @return int
     */
    public function getRoleId() {
        return $this->roleId;
    }

    /**
     * @param string $roleId
     * @return self
     */
    public function setUserId($userId) {
        $this->userId = $userId;
        return $userId;
    }

    /**
     * @return int
     */
    public function getUserId() {
        return $this->userId;
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
     * @param \Alaress_Date_Format $modifiedDate
     * @return self
     */
    public function setDeletedDate($deletedDate) {
        $this->deletedDate = $deletedDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDeletedDate() {
        return $this->deletedDate;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return $this->getDeletedDate() != null;
    }
}
