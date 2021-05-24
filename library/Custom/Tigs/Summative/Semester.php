<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Alaress_Date_Format;

/**
 * Strongly-typed representation of a semester
 */
class Semester
{

    /**
     * A number designating this semester's place in an ordered sequence. Eg 1, 2
     *
     * @var int
     */
    private $ordinal;

    /** @var Alaress_Date_Format */
    private $start;

    /** @var Alaress_Date_Format */
    private $finish;


    /**
     * @param int                 $ordinal
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $finish
     */
    public function __construct(int $ordinal, Alaress_Date_Format $start, Alaress_Date_Format $finish)
    {
        $this->ordinal = $ordinal;
        $this->start   = $start;
        $this->finish  = $finish;
    }


    /**
     * @return int
     */
    public function getOrdinal(): int
    {
        return $this->ordinal;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getStart(): Alaress_Date_Format
    {
        return $this->start;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getFinish(): Alaress_Date_Format
    {
        return $this->finish;
    }
}
