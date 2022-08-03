<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Node;

use Alaress_Date_Format;
use JsonSerializable;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits;

/**
 * Model for curriculum learning nodes.
 *
 * A node represents a generic concept within a curriculum: it can represent
 * the root of the curriculum, a learning outcome or any point between.
 */
class Model implements ISoftDeletable, JsonSerializable
{
    use Traits\Model\Id;
    use Traits\Model\CreatedAtDate;
    use Traits\Model\DeletedAtDate;

    /**
     * This node's parent (if there is one)
     *
     * @var Model|null
     */
    protected ?Model $parent;

    /**
     * This node's children (if it has any)
     *
     * @var GenericCollection<Model>
     */
    protected GenericCollection $children;

    /**
     * The learning level this node is associated with, if any.
     */
    protected ?LearningLevel\Model $learningLevel;

    /**
     * The learning area this node is associated with, if any.
     */
    protected ?LearningArea\Model $learningArea;

    /**
     * User-friendly name
     */
    protected string $name = '';

    /**
     * Curriculum code (if this node has one)
     */
    protected string $code = '';

    /**
     * Reference used by the browser for fetching outcomes.
     * Possibly a URI used in an RDF query.
     */
    protected ?string $reference = null;

    /**
     * User-friendly description
     */
    protected string $description = '';

    /**
     * The order in the tree structure that this node exists
     */
    protected int $order = 0;

    /**
     * When was this modified?
     */
    protected Alaress_Date_Format $modifiedAt;

    /**
     * Constructor: build this element with a new, empty collection.
     */
    public function __construct()
    {
        $this->children = new GenericCollection(self::class);
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt(Alaress_Date_Format $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt(): Alaress_Date_Format
    {
        return $this->modifiedAt;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * @param string|null $reference
     * @return $this
     */
    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return LearningArea\Model|null
     */
    public function getLearningArea(): ?LearningArea\Model
    {
        return $this->learningArea;
    }

    /**
     * @param LearningArea\Model|null $learningArea
     * @return $this
     */
    public function setLearningArea(?LearningArea\Model $learningArea): self
    {
        $this->learningArea = $learningArea;
        return $this;
    }

    /**
     * @return LearningLevel\Model|null
     */
    public function getLearningLevel(): ?LearningLevel\Model
    {
        return $this->learningLevel;
    }

    /**
     * @param LearningLevel\Model|null $learningLevel
     * @return $this
     */
    public function setLearningLevel(?LearningLevel\Model $learningLevel): self
    {
        $this->learningLevel = $learningLevel;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @param int $order
     * @return $this
     */
    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return Model|null
     */
    public function getParent(): ?Model
    {
        return $this->parent;
    }

    /**
     * @param Model|null $parent
     * @return $this
     */
    public function setParent(?Model $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @param  Model $child
     * @return $this
     */
    public function addChild(Model $child)
    {
        $this->getChildren()->add($child);
        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getChildren(): GenericCollection
    {
        return $this->children;
    }

    /**
     * @param GenericCollection $children
     * @return $this
     */
    public function setChildren(GenericCollection $children): self
    {
        $this->children = $children;
        return $this;
    }

    /**
     * Builds a breadcrumb trail for this curriculum node.
     *
     * @param string $delimiter
     * @return string
     */
    public function buildBreadcrumb(string $delimiter)
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
