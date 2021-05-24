<?php

/**
 * Model for curriculum learning areas.
 *
 * A learning area is an area of study - common examples include:
 * * English
 * * Maths
 * * Science
 * I think you get the idea.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\LearningArea;

use Alaress_Date_Format;
use JsonSerializable;
use Schoolbox\Curriculum\Source;
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
     * The curriculum source this learning area belongs to
     *
     * @var Source\Model
     */
    protected $source;

    /**
     * User-friendly name
     *
     * @var string
     */
    protected $name;

    /**
     * Reference used by the browser for fetching outcomes.
     * Possibly a URI used in a RDF query.
     *
     * @var string
     */
    protected $reference;

    /**
     * The order of this item in lists (higher number - later in list)
     *
     * @var int
     */
    protected $order = 0;

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
     * @param  Source\Model $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @return Source\Model
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param  string $reference
     * @return $this
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param  int $order
     *
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
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
            'source'      => $this->getSource(),
            'name'        => $this->getName(),
            'description' => $this->getDescription(),
            'reference'   => $this->getReference(),
        );
    }
}
