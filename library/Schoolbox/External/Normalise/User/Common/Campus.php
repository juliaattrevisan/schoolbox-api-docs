<?php
/**
 * Campus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Common;


class Campus {

    /**
     * Identifier
     * (May be synonymous with $code)
     *
     * @var string
     */
    private $id = '';

    /**
     * Campus name
     *
     * @var string
     */
    private $name = '';

    /**
     * Campus code
     *
     * @var string
     */
    private $code = '';

    /**
     * Constructor
     *
     * Immutable
     *
     * @param $id
     * @param $name
     * @param $code
     */
    public function __construct($id, $name, $code) {
        $this->setId($id);
        $this->setName($name);
        $this->setCode($code);
    }

    /* Getters/Setters
     */

    /**
     * Set code
     *
     * @param string $code
     */
    private function setCode($code)
    {
        $this->code = $code;
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
     * Set id
     *
     * @param string $id
     */
    private function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return string
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
    private function setName($name)
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
}