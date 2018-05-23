<?php

namespace Schoolbox\Admin\Timezone;

/**
 * Class Timesone
 * @package Schoolbox\Admin
 */
class Timezone
{
    /** @var string $timezone */
    protected $timezone;

    /** @var int $offset */
    protected $offset;


    /**
     * Timezone constructor.
     *
     * @param $timezone
     * @param $offset
     */
    public function __construct($timezone, $offset) {
        $this->timezone = $timezone;
        $this->offset = $offset;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->timezone;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return "(+".gmdate("H:i", $this->offset).") ".$this->timezone;
    }
}

