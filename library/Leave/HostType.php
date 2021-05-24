<?php

/**
 * HostType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Leave;

class HostType
{

    /**
     * Host type: mother
     *
     * @var int
     */
    const TYPE_MOTHER   = 1;

    /**
     * Host type: father
     *
     * @var int
     */
    const TYPE_FATHER   = 2;

    /**
     * Host type: guardian other than mother or father
     *
     * @var int
     */
    const TYPE_GUARDIAN = 3;

    /**
     * Host type: self (no host)
     *
     * @var int
     */
    const TYPE_SELF     = 4;

    /**
     * Host type: other (whatever that is)
     *
     * @var int
     */
    const TYPE_OTHER    = 5;

    /**
     * Identifier
     *
     * @var int
     */
    private $id = -1;

    /**
     * Name of the host type
     *
     * @var string
     */
    private $name = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
