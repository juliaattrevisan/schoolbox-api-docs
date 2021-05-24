<?php

/**
 * Model for a curriculum source.
 *
 * A curriculum source is a remote source from which curricula can be imported
 * from - examples include:
 * * Australian Curriculum (http://www.australiancurriculum.edu.au/)
 * * AusVELS (http://ausvels.vcaa.vic.edu.au/)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use Alaress_Date_Format;
use JsonSerializable;
use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable, JsonSerializable
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * User-friendly name
     *
     * @var string
     */
    protected $name;

    /**
     * The PHP class name that this model points to
     *
     * @var string
     */
    protected $class;

    /**
     * User-friendly description
     *
     * @var string
     */
    protected $description;

    /**
     * @var Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @param  string $class
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param  string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param  string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *       which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return array(
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'description' => $this->getDescription(),
        );
    }
}
