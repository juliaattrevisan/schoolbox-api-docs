<?php
/**
 * Tag Cloud Model
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\TagCloud;

use Schoolbox\User;

class Model {

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * Model constructor.
     */
    public function __construct()
    {
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
     * @param string $name
     * @return self
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
     * @param string $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }    
}