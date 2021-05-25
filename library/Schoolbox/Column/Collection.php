<?php

/**
 * Collection column type - hydrates with a lazy model collection, and dehydrates those models' IDs.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Collection implements IColumnType
{

    /**
     * The property to set on the model when hydrating, or get when dehydrating.
     * If not set, then the mapper's ID property is used.
     *
     * @var string
     */
    protected $identifier_property;

    /**
     * @param  string $dehydrate_property
     *
     * @return $this
     */
    public function setIdentifierProperty($dehydrate_property)
    {
        $this->identifier_property = $dehydrate_property;
        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifierProperty()
    {
        return $this->identifier_property;
    }


    /**
     * Creates an Collection of the specified type.
     *
     * @param array             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return \Schoolbox\Collection
     */
    public function hydrate($value, $mapper = null)
    {
        $collection = $mapper
            ? $mapper->createCollection()
            : new \Schoolbox\Collection();

        if (is_array($value)) {
            // Figure out the identifier property to set on the model.
            $id_property = $this->getIdentifierProperty();
            $identifier_method  = isset($id_property) ?
                $mapper->getSetMethodName($id_property) :
                $mapper->getSetIdentifierMethodName();

            foreach ($value as $v) {
                $model = $mapper->createModel();
                $model->$identifier_method($v);
                $collection->add($model);
            }
        }

        return $collection;
    }


    /**
     * Important! This is called by diff() as well as dehydrate(), so we do need
     * to retain it.
     *
     * @param mixed $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return mixed An array of the identifier properties from `$value`, or
     *               null if `$value` isn't set
     */
    public function dehydrate($value, $mapper = null)
    {
        if (!isset($value)) {
            return null;
        }

        // $value shouldn't ever be scalar or an array?
        // It should always be a collection, because that's what it always
        // hydrates to
        if (is_scalar($value) || is_array($value)) {
            return $value;
        }
        if (!($idProperty = $this->getIdentifierProperty())) {
            $prop = $mapper->getIdentifier();
            $idProperty = $prop['property'];
        }

        // Handle the case where `$value` may contain scalars:
        // This can happen if someone just set a collection of ID values into
        // the model
        $result = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = $item;
            } else {
                $result[] = $mapper->getModelProperty($item, $idProperty);
            }
        }

        return $result;
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
