<?php

namespace Schoolbox\Admin\Timezone;

/**
 * Class Timezone
 * @package Schoolbox\Admin
 */
class Timezone
{
    /** @var string $timezone */
    private $timezone;

    /** @var int $offset */
    private $offset;

    /** @var string $name */
    private $name;


    /**
     * Timezone constructor.
     *
     * @param string $timezone
     * @param int $offset
     */
    public function __construct($timezone, $offset)
    {
        $this->timezone = $timezone;
        $this->offset = str_replace(':', '', $offset);

        /* Some timezones have an underscore in them, which looks a bit dumb
         * Because phplib, have to do it here
         */
        $formattedTimezone = str_replace('_', ' ', $timezone);

        $this->name = sprintf('(GMT%s) %s', $offset, $formattedTimezone);
    }

    /**
     * Returns identifier for this timezone.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->timezone;
    }

    /**
     * Returns a label for this timezone.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns this timezone's offset from GMT, in HHMM format.
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }
}
