<?php
/**
 * House
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Common;


class House {

    /**
     * Identifier
     * (May be synonymous with $code)
     *
     * @var string
     */
    private $id = '';

    /**
     * The official formal name of the house
     *
     * @var string
     */
    private $name = '';

    /**
     * A shortened abbreviation used for the house
     * (May be synonymous with $id)
     *
     * @var string
     */
    private $code = '';

    /**
     * A housemaster is just another normalised user
     *
     * @var Base
     */
    private $housemaster = null;

    /**
     * This is a boarding house
     *
     * @var bool
     */
    private $boarder = false;

    /**
     * Constructor
     *
     * Immutable
     *
     * @param $id
     * @param $name
     * @param $code
     * @param $housemaster
     * @param $boarder
     */
    public function __construct($id, $name, $code, $housemaster, $boarder) {
        $this->setId($id);
        $this->setName($name);
        $this->setCode($code);
        $this->setHousemaster($housemaster);
        $this->setBoarder($boarder);
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

    /**
     * Set housemaster
     *
     * @param \Schoolbox\External\Normalise\User\Base $housemaster
     */
    private function setHousemaster($housemaster)
    {
        $this->housemaster = $housemaster;
    }

    /**
     * Get housemaster
     *
     * @return \Schoolbox\External\Normalise\User\Base
     */
    public function getHousemaster()
    {
        return $this->housemaster;
    }

    /**
     * Set boarder
     *
     * @param boolean $boarder
     */
    private function setBoarder($boarder)
    {
        $this->boarder = $boarder;
    }

    /**
     * Get boarder
     *
     * @return boolean
     */
    public function getBoarder()
    {
        return $this->boarder;
    }
}