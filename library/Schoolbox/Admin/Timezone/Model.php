<?php

namespace Schoolbox\Admin\Timezone;

/**
 * Class Model
 * @package Schoolbox\Admin
 */
class Model
{
    /** @var string $timezone */
    protected $timezone;

    /**
     * Timezone constructor.
     * @param $timezone
     */
    public function __construct($timezone) {
        $this->timezone = $timezone;
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
        return $this->timezone;
    }
}

