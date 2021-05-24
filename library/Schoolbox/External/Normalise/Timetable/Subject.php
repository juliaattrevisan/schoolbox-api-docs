<?php

/**
 * Subject
 *
 * This is the layer above a class, and is the subject that the class relates to (e.g. 'English')
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Timetable;

abstract class Subject
{

    /**
     * A unique identifier
     * @var string
     */

    protected $id = '';


    /**
     * The subject name (e.g. 'English'
     * @var string
     */

    protected $name = '';

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

    /**
     * Set name
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
