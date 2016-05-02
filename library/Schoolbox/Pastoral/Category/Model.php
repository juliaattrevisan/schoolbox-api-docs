<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Category;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use JsonSerializable;

class Model implements ISoftDeletable, JsonSerializable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $pastoralTypeId;

    /** @var string */
    protected $name;

    /** @var string */
    protected $externalCode;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * @param string $name
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
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $pastoralTypeId
     * @return self
     */
    public function setPastoralTypeId($pastoralTypeId) {
        $this->pastoralTypeId = $pastoralTypeId;
        return $this;
    }

    /**
     * @return int
     */
    public function getPastoralTypeId() {
        return $this->pastoralTypeId;
    }

    /**
     * @param string $externalCode
     * @return self
     */
    public function setExternalCode($externalCode) {
        $this->externalCode = $externalCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getExternalCode() {
        return $this->externalCode;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return !$this->getDeletedAt() instanceof \Alaress_Date_NullDate;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize() {
        return array(
            'id'          => $this->getId(),
            'name'        => $this->getName(),
        );
    }

}
