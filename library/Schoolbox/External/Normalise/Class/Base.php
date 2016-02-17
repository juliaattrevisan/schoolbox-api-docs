<?php
/**
 * Class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\ClassCode;

abstract class Base {

    /**
     * A unique identifier
     * @var string
     */

    protected $id = '';

    /**
     * Getters/Setters
     */

    /**
     * Set id
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}