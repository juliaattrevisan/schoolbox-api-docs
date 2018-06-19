<?php

namespace Schoolbox\Admin\Timezone;

/**
 * Class Timezone
 * @package Schoolbox\Admin
 */
class Timezone
{
    /** @var string $timezone */
    protected $timezone;

    /** @var int $offset */
    protected $offset;

    /** @var string $name */
    protected $name;


    /**
     * Timezone constructor.
     *
     * @param string $timezone
     * @param int $offset
     */
    public function __construct($timezone, $offset) {
        $this->timezone = $timezone;
        $this->offset = $offset;

        /* Some timezones have an underscore in them, which looks a bit dumb
         * Because phplib, have to do it here
         */
        $formattedTimezone = str_replace('_', ' ', $timezone);

        $this->name = sprintf('(GMT%s) %s', $offset, $formattedTimezone);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->timezone;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}

