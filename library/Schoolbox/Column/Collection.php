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
     * @param array|string      $value Mapper infrastructure always passes a string.
     * @param \Schoolbox\Mapper $mapper
     *
     * @return \Schoolbox\Collection
     */
    public function hydrate($value, $mapper = null)
    {
        $collection = $mapper
            ? $mapper->createCollection()
            : new \Schoolbox\Collection();

        /* In the current mapper infrastructure, $value is always a string, never an array - so this code
         * is never executed. It is however retained to make in symmetrical with dehydrate()
         */
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
     * Important! This is called by diff() as well as dehydrate(), so we do need to retain it.
     *
     * @param \Schoolbox\Collection $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return array
     */
    public function dehydrate($value, $mapper = null)
    {
        if (!isset($value)) {
            return null;
        }
        if (is_scalar($value) || is_array($value)) {
            return $value;
        }
        if (!($id_property = $this->getIdentifierProperty())) {
            $prop = $mapper->getIdentifier();
            $id_property = $prop['property'];
        }
        return $value->getColumnValues($id_property);
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
