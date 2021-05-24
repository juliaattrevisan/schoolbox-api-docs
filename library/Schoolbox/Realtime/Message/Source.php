<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

class Source
{

    /** @var int */
    protected $id;
    /** @var NS */
    protected $namespace;
    /** @var string */
    protected $key;
    /** @var string */
    protected $roleType;
    /** @var string */
    protected $description;


    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param \Schoolbox\Realtime\Message\NS $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return \Schoolbox\Realtime\Message\NS
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param string $roleType
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
    }

    /**
     * @return string
     */
    public function getRoleType()
    {
        return $this->roleType;
    }
}
