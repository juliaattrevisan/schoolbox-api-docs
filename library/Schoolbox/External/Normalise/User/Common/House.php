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
use Schoolbox\External\Normalise\User\Staff;


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
     * @var Staff
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
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup() {
        $this->setHousemaster(new Staff());
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
     * Set id
     *
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
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
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
     * @param Staff $housemaster
     * @return $this
     */
    public function setHousemaster($housemaster)
    {
        $this->housemaster = $housemaster;
        return $this;
    }

    /**
     * Get housemaster
     *
     * @return Staff
     */
    public function getHousemaster()
    {
        return $this->housemaster;
    }

    /**
     * Set boarder
     *
     * @param boolean $boarder
     * @return $this
     */
    public function setBoarder($boarder)
    {
        $this->boarder = $boarder;
        return $this;
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