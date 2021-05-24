<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class TimetableDefinitions
{

    /** @var int */
    private $daysMax;

    /** @var array */
    private $days;

    /** @var int */
    private $periodMax;


    /**
     * @param int   $daysMax
     * @param array $days
     * @param int   $periodMax
     */
    public function __construct(int $daysMax, array $days, int $periodMax)
    {
        $this->daysMax   = $daysMax;
        $this->days      = $days;
        $this->periodMax = $periodMax;
    }


    /**
     * @return int
     */
    public function getDaysMax(): int
    {
        return $this->daysMax;
    }


    /**
     * @return array
     */
    public function getDays(): array
    {
        return $this->days;
    }


    /**
     * @return int
     */
    public function getPeriodMax(): int
    {
        return $this->periodMax;
    }
}
