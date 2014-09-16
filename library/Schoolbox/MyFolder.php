<?php
/**
 * Model for the folders in My Files.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

class MyFolder {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var MyFolder
     */
    protected $parent;

    /**
     * @param  int   $id
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
     * @param  string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param  User  $owner
     * @return $this
     */
    public function setOwner($owner) {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner() {
        return $this->owner;
    }

    /**
     * @param  MyFolder $parent
     * @return $this
     */
    public function setParent($parent) {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return MyFolder
     */
    public function getParent() {
        return $this->parent;
    }

}
