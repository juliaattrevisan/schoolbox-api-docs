<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Type;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var string */
    protected $externalCode;

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
    public function getId() {
        return $this->id;
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
