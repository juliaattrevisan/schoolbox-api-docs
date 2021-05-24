<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

/**
 * Represents a House Event that a student participated in
 */
class HouseEvent
{

    /** @var string */
    private $name;

    /** @var string */
    private $status;


    /**
     * @param string $name
     * @param string $status
     */
    public function __construct(string $name, string $status)
    {
        $this->name   = $name;
        $this->status = $status;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
