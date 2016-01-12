<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Tags;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $pastoralId;

    /** @var string */
    protected $name;

    /** @var \Alaress_Date_Format */
    protected $deletedDate;

    /**
     * @param string $hash
     * @return self
     */
    public function setName($name) {
        $this->name = $name;
        return $name;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
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
    public function getPastoralId() {
        return $this->pastoralId;
    }

    /**
     * @param int $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
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
