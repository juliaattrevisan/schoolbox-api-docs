<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class StudentTimetable
{

    /** @var string */
    private $date;
    /** @var int */
    private $dayCode;
    /** @var array */
    private $periods;


    /**
     * @param string $date
     * @param int    $dayCode
     * @param array  $periods
     */
    public function __construct(
        string $date,
        int $dayCode,
        array $periods
    ) {
        $this->date    = $date;
        $this->dayCode = $dayCode;
        $this->periods = $periods;
    }


    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }


    /**
     * @return int
     */
    public function getDayCode(): int
    {
        return $this->dayCode;
    }


    /**
     * @return array
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }
}
