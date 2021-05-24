<?php

/**
 * Model column type - hydrates with a lazy model, and dehydrates that model's
 * ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

use Alaress\Cache;
use Schoolbox\Mapper;

class Model implements IColumnType
{

    /** @var Cache|object[] */
    private $cache = [];


    /**
     * Model Column constructor.
     */
    public function __construct()
    {
        $this->cache = new Cache(function (Mapper $mapper, $value) {
            if (null === $value) {
                return null;
            }

            $model = $mapper->createModel();

            if (!$model) {
                // the model failed to instantiate, so return null accordingly
                return null;
            }

            // Figure out the identifier property to set on the model.
            $id_property = $this->getIdentifierProperty();

            $identifier_method  = isset($id_property)
                ? $mapper->getSetMethodName($id_property)
                : $mapper->getSetIdentifierMethodName();

            // Hydrate the identifier before setting it, if possible
            // Prefer this column's configured identifier, fall back to mapper's configured identifier
            $identifier = isset($id_property)
                ? $mapper->getMap()[$id_property]
                : $mapper->getIdentifier();

            $identifierHydrator = $identifier['hydrator'] ?? null;
            if ($identifierHydrator instanceof IColumnType) {
                $identifierJoin       = $identifier['join'] ?? null;
                $identifierJoinMapper = $identifierJoin ? $mapper->getJoinMapper($identifierJoin) : null;
                $model->$identifier_method($identifierHydrator->hydrate($value, $identifierJoinMapper));
            } else {
                $model->$identifier_method($value);
            }

            return $model;
        });
    }

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
     * Hydrates this column with a model which is empty, except for its
     * identifier property.
     *
     * @example hydrating "2" with the UserMapper creates a new User object,
     *          with its ID property set to 2.
     *          If setIdentifierProperty() was called on this column object, that
     *          property will be hydrated instead.
     *
     * @param mixed  $value
     * @param Mapper $mapper
     *
     * @return object|null
     */
    public function hydrate($value, $mapper = null)
    {
        return $this->cache->get(sprintf("'%s'.'%s'", get_class($mapper), $value), [ $mapper, $value ]);
    }

    /**
     * @param mixed             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return mixed
     * * If $value is null, returns null (represents a missing foreign key).
     * * If $value is a scalar value, returns $value. (user probably meant to
     *   set an ID value - might as well allow this)
     * * If $this->setIdentifierProperty() was called, returns the value of that
     *   field from $value
     * * Otherwise, returns ID of $value
     */
    public function dehydrate($value, $mapper = null)
    {
        if (!isset($value)) {
            return null;
        }
        if (is_scalar($value)) {
            return is_numeric($value) ? (int)$value : $value;
        }
        $id_property = $this->getIdentifierProperty();
        $identifier_method  = isset($id_property) ?
            $mapper->getGetMethodName($id_property) :
            $mapper->getGetIdentifierMethodName();
        return $value->$identifier_method();
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
