<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;


use Schoolbox\Collection;
use Schoolbox\Sync;

class Model
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $roleType;

    /** @var bool */
    protected $enabled;

    /** @var int */
    protected $sequence;

    /** @var string */
    protected $name;

    /** @var Collection|Sync\Condition\Model[] */
    protected $conditions;

    /** @var Collection|Sync\Effect\Model[] */
    protected $effects;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @param string $roleType
     * @return static
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }


    /**
     * @deprecated dynamic mapper use only
     * @return bool
     */
    public function getEnabled()
    {
        return $this->isEnabled();
    }


    /**
     * @param boolean $enabled
     * @return static
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
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
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
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return Collection|Sync\Condition\Model[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }


    /**
     * @param Collection|Sync\Condition\Model[] $conditions
     * @return static
     */
    public function setConditions($conditions)
    {
        $this->conditions = $conditions;
        return $this;
    }


    /**
     * @return Collection|Sync\Effect\Model[]
     */
    public function getEffects()
    {
        return $this->effects;
    }


    /**
     * @param Collection|Sync\Effect\Model[] $effects
     * @return static
     */
    public function setEffects($effects)
    {
        $this->effects = $effects;
        return $this;
    }

}