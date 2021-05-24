<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

use Alaress_Date_Format;

/**
 * TODO what does UD stand for? Suspect it may be 'user-defined', if so, need to update all var names once confirmed
 */
class StudentUD
{



    /** @var array */
    private $udCodes;

    /** @var array */
    private $udDates;

    /** @var array */
    private $udFlags;

    /** @var Alaress_Date_Format|null */
    private $updatedOn;


    /**
     * @param array                    $udFlags
     * @param array                    $udCodes
     * @param array                    $udDates
     * @param Alaress_Date_Format|null $updatedOn
     */
    public function __construct(array $udCodes, array $udDates, array $udFlags, ?Alaress_Date_Format $updatedOn)
    {

        $this->udCodes   = $udCodes;
        $this->udDates   = $udDates;
        $this->udFlags   = $udFlags;
        $this->updatedOn = $updatedOn;
    }





    /**
     * @return array
     */
    public function getUdCodes(): array
    {
        return $this->udCodes;
    }


    /**
     * @return array
     */
    public function getUdDates(): array
    {
        return $this->udDates;
    }

    /**
     * @return array
     */
    public function getUdFlags(): array
    {
        return $this->udFlags;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getUpdatedOn(): ?Alaress_Date_Format
    {
        return $this->updatedOn;
    }
}
