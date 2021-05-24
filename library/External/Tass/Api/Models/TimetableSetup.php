<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class TimetableSetup
{

    /**
     * Will be null if fetched from API with 'contacts' set to false
     *
     * @var null|TimetableSetupContact
     */
    private $contact;

    /** @var bool */
    private $calFlag;

    /** @var bool */
    private $clashFlag;

    /** @var int */
    private $currentSemester;

    /** @var int */
    private $currentYear;

    /** @var string */
    private $intValidFlag;

    /** @var bool */
    private $isMultiTT;

    /** @var bool */
    private $isUnitised;

    /** @var int */
    private $maxDays;

    /** @var int */
    private $maxPeriods;

    /** @var string */
    private $mode;

    /** @var bool */
    private $multiTtFlag;

    /** @var int */
    private $recordId;

    /** @var bool */
    private $unitFlag;


    /**
     * @param TimetableSetupContact|null $contact
     * @param bool                       $calFlag
     * @param bool                       $clashFlag
     * @param int                        $currentSemester
     * @param int                        $currentYear
     * @param string                     $intValidFlag
     * @param bool                       $isMultiTT
     * @param bool                       $isUnitised
     * @param int                        $maxDays
     * @param int                        $maxPeriods
     * @param string                     $mode
     * @param bool                       $multiTtFlag
     * @param int                        $recordId
     * @param bool                       $unitFlag
     */
    public function __construct(
        ?TimetableSetupContact $contact,
        bool $calFlag,
        bool $clashFlag,
        int $currentSemester,
        int $currentYear,
        string $intValidFlag,
        bool $isMultiTT,
        bool $isUnitised,
        int $maxDays,
        int $maxPeriods,
        string $mode,
        bool $multiTtFlag,
        int $recordId,
        bool $unitFlag
    ) {
        $this->contact         = $contact;
        $this->calFlag         = $calFlag;
        $this->clashFlag       = $clashFlag;
        $this->currentSemester = $currentSemester;
        $this->currentYear     = $currentYear;
        $this->intValidFlag    = $intValidFlag;
        $this->isMultiTT       = $isMultiTT;
        $this->isUnitised      = $isUnitised;
        $this->maxDays         = $maxDays;
        $this->maxPeriods      = $maxPeriods;
        $this->mode            = $mode;
        $this->multiTtFlag     = $multiTtFlag;
        $this->recordId        = $recordId;
        $this->unitFlag        = $unitFlag;
    }


    /**
     * @return TimetableSetupContact|null
     */
    public function getContact(): ?TimetableSetupContact
    {
        return $this->contact;
    }


    /**
     * @return bool
     */
    public function isCalFlag(): bool
    {
        return $this->calFlag;
    }


    /**
     * @return bool
     */
    public function isClashFlag(): bool
    {
        return $this->clashFlag;
    }


    /**
     * @return int
     */
    public function getCurrentSemester(): int
    {
        return $this->currentSemester;
    }


    /**
     * @return int
     */
    public function getCurrentYear(): int
    {
        return $this->currentYear;
    }


    /**
     * @return string
     */
    public function getIntValidFlag(): string
    {
        return $this->intValidFlag;
    }


    /**
     * @return bool
     */
    public function isMultiTT(): bool
    {
        return $this->isMultiTT;
    }


    /**
     * @return bool
     */
    public function isUnitised(): bool
    {
        return $this->isUnitised;
    }


    /**
     * @return int
     */
    public function getMaxDays(): int
    {
        return $this->maxDays;
    }


    /**
     * @return int
     */
    public function getMaxPeriods(): int
    {
        return $this->maxPeriods;
    }


    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }


    /**
     * @return bool
     */
    public function isMultiTtFlag(): bool
    {
        return $this->multiTtFlag;
    }


    /**
     * @return int
     */
    public function getRecordId(): int
    {
        return $this->recordId;
    }


    /**
     * @return bool
     */
    public function isUnitFlag(): bool
    {
        return $this->unitFlag;
    }
}
