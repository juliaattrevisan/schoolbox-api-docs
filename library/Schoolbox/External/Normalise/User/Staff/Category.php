<?php

/**
 * Category
 *
 * $type
 *   +--- ($code, $description)
 *
 * e.g.
 *
 * TEACHER
 *   +--- (CAT, Catering Crew)
 *   +--- (LIB, Librarians)
 *   ...
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Staff;

class Category
{

    /**
     * Staff category code
     * Usually an abbreviation
     *
     * @var string
     */
    private $code = '';

    /**
     * Staff category description
     * A pretty name/description corresponding to code
     *
     * @var string
     */
    private $description = '';

    /**
     * Staff category type
     * An arbitrary grouping for staff category codes
     *
     * @var string
     */
    private $type = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
