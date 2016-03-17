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
    protected $postDate;

    /** @var \Alaress_Date_Format */
    protected $modifiedDate;

    /** @var \Alaress_Date_Format */
    protected $deletedDate;

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
     * @param \Alaress_Date_Format $deletedDate
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
     * @param \Alaress_Date_Format $modifiedDate
     * @return self
     */
    public function setModifiedDate($modifiedDate) {
        $this->modifiedDate = $modifiedDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getModifiedDate() {
        return $this->modifiedDate;
    }

    /**
     * @param \Alaress_Date_Format $postDate
     * @return self
     */
    public function setPostDate($postDate) {
        $this->postDate = $postDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPostDate() {
        return $this->postDate;
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
