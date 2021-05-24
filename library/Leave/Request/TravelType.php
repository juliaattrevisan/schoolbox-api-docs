<?php

/**
 * Model for travel types.
 *
 * These are a part of the leave request object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Leave_Request_TravelType
{

    const TYPE_NONE  = 0;
    const TYPE_CAR   = 1;
    const TYPE_TAXI  = 2;
    const TYPE_TRAIN = 3;
    const TYPE_BUS   = 4;
    const TYPE_WALK  = 5;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * For ordering the dropdown
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * Set sequence
     *
     * @param int $sequence
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * Get sequence
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }
}
