<?php

/**
 * Config
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace DailyQuote;

class Config
{

    /**
     * Identifier
     *
     * @var null
     */
    private $id = null;

    /**
     * Config name
     *
     * @var string
     */
    private $name = '';

    /**
     * Config value
     *
     * @var string
     */
    private $value = '';

    /* Getters/Setters
     */

    /**
     * Set id
     *
     * @param null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return null
     */
    public function getId()
    {
        return $this->id;
    }

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
     * Set value
     *
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
