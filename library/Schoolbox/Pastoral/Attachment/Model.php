<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Attachment;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $pastoralId;

    /** @var string */
    protected $hash;

    /** @var \Alaress_Date_Format */
    protected $datetimePosted;

    /** @var \Alaress_Date_Format */
    protected $datetimeModified;

    /** @var \Alaress_Date_Format */
    protected $datetimeDeleted;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $filename;

    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash) {
        $this->hash = $hash;
        return $this;
    }

    /**
     * @return string
     */
    public function getHash() {
        return $this->hash;
    }

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon) {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string
     */
    public function getIcon() {
        return $this->icon;
    }

    /**
     * @param string $filename
     * @return self
     */
    public function setFilename($filename) {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename() {
        return $this->filename;
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
    public function getPastoralId() {
        return $this->pastoralId;
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
     * @param \Alaress_Date_Format $datetimeModified
     * @return self
     */
    public function setDatetimeModified($datetimeModified) {
        $this->datetimeModified = $datetimeModified;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimeModified() {
        return $this->datetimeModified;
    }

    /**
     * @param \Alaress_Date_Format $datetimePosted
     * @return self
     */
    public function setDatetimePosted($datetimePosted) {
        $this->datetimePosted = $datetimePosted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimePosted() {
        return $this->datetimePosted;
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
