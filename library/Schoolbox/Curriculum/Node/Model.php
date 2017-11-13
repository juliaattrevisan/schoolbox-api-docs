<?php
/**
 * Model for curriculum learning nodes.
 *
 * A node represents a generic concept within a curriculum: it can represent
 * the root of the curriculum, a learning outcome or any point between.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Node;

use Alaress_Date_Format;
use JsonSerializable;
use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
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
     * This node's parent (if there is one)
     *
     * @var Model
     */
    protected $parent;

    /**
     * This node's children (if it has any)
     *
     * @var Collection|Model[]
     */
    protected $children;

    /**
     * The learning level this node is associated with
     *
     * @var LearningLevel\Model
     */
    protected $learningLevel;

    /**
     * The learning area this node is associated with
     *
     * @var LearningArea\Model
     */
    protected $learningArea;

    /**
     * User-friendly name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Curriculum code (if this node has one)
     *
     * @var string
     */
    protected $code = '';

    /**
     * Reference used by the browser for fetching outcomes.
     * Possibly a URI used in a RDF query.
     *
     * @var string
     */
    protected $reference;

    /**
     * User-friendly description
     *
     * @var string
     */
    protected $description = '';

    /**
     * The order in the tree structure that this node exists
     *
     * @var int
     */
    protected $order = 0;

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
     * Constructor: build this element with a new, empty collection.
     */
    public function __construct()
    {
        $this->setChildren(new Collection());
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
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param  string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return LearningArea\Model
     */
    public function getLearningArea()
    {
        return $this->learningArea;
    }

    /**
     * @param  LearningArea\Model $learningArea
     * @return $this
     */
    public function setLearningArea($learningArea)
    {
        $this->learningArea = $learningArea;
        return $this;
    }

    /**
     * @return LearningLevel\Model
     */
    public function getLearningLevel()
    {
        return $this->learningLevel;
    }

    /**
     * @param  LearningLevel\Model $learningLevel
     * @return $this
     */
    public function setLearningLevel($learningLevel)
    {
        $this->learningLevel = $learningLevel;
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
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param  Model $parent
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @param  Model $child
     * @return $this
     */
    public function addChild($child)
    {
        $this->getChildren()->add($child);
        return $this;
    }

    /**
     * @return Collection|Model[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param  Collection|Model[] $children
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children = $children;
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
     * Builds a breadcrumb trail for this curriculum node.
     *
     * @param string $delimiter
     * @return string
     */
    public function buildBreadcrumb($delimiter)
    {
        $parts   = [];
        $current = $this;
        $i       = 0;
        while ($current instanceof static && $i++ <= 50) {
            array_unshift($parts, $current->getName());
            $current = $current->getParent();
        }
        array_pop($parts);
        return implode($delimiter, $parts);
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
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'code'        => $this->getCode(),
            'description' => $this->getDescription(),
            'reference'   => $this->getReference(),
            'children'    => $this->getChildren(),
            'area'        => $this->getLearningArea(),
            'level'       => $this->getLearningLevel(),
            'breadcrumb'  => $this->buildBreadcrumb(' > '),
        ];
    }
}
