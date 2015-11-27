<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use Schoolbox\Sync;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use UnexpectedValueException;

/**
 * Mapper for custom hydrators
 */
class Model
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * The class name for this custom hydrator
     *
     * @var string
     */
    protected $class;

    /**
     * The role type. One of the following constants:
     * * `\Schoolbox\Role::STAFF`
     * * `\Schoolbox\Role::STUDENT`
     * * `\Schoolbox\Role::PARENT`.
     *
     * @var string
     */
    protected $roleType;

    /**
     * Is this hydrator enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * The order of execution of this custom hydrator. Lower numbers run first.
     *
     * @var int
     */
    protected $sequence;

    /**
     * The actual hydrator this model points to.
     *
     * @var HydratorInterface
     */
    protected $hydrator;

    /**
     * Loads the hydrator that this model points to.
     *
     * @return $this
     * @throws UnexpectedValueException if class doesn't exist, or it's not instanceof HydratorInterface
     */
    public function loadHydratorFromClass()
    {
        $factory = new Factory();
        $this->setHydrator($factory->produceFromModel($this));
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
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
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
    public function getRoleType()
    {
        return $this->roleType;
    }

    /**
     * @param  string $roleType
     * @return $this
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param  bool $enabled
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param  int $sequence
     * @return $this
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return HydratorInterface
     */
    public function getHydrator()
    {
        return $this->hydrator;
    }

    /**
     * @param  HydratorInterface $hydrator
     * @return $this
     */
    public function setHydrator($hydrator)
    {
        $this->hydrator = $hydrator;
        return $this;
    }
}
