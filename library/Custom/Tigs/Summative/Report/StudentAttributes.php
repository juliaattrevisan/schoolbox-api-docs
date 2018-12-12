<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

/**
 * Encapsulates relevant student attributes to help determine which report components are relevant
 */
class StudentAttributes
{

    /** @var bool */
    private $isIb;

    /** @var int */
    private $sbSem;

    /** @var int */
    private $term;

    /** @var int */
    private $curriculumYearLevel;

    /** @var int */
    private $curriculumSemester;


    /**
     * @param bool $isIb
     * @param int  $sbSem
     * @param int  $term
     * @param int  $curriculumYearLevel
     * @param int  $curriculumSemester
     */
    public function __construct(bool $isIb, int $sbSem, int $term, int $curriculumYearLevel, int $curriculumSemester)
    {
        $this->isIb                = $isIb;
        $this->sbSem               = $sbSem;
        $this->term                = $term;
        $this->curriculumYearLevel = $curriculumYearLevel;
        $this->curriculumSemester  = $curriculumSemester;
    }


    /**
     * Returns whether the student is MYP (IB Middle Years Programme)
     *
     * @return bool
     */
    public function isMyp(): bool
    {
        return $this->curriculumYearLevel <= 10;
    }


    /**
     * Returns whether the student is DP (IB Diploma Programme)
     *
     * @return bool
     */
    public function isDp(): bool
    {
        return $this->curriculumYearLevel >= 11 && $this->isIb;
    }


    /**
     * Returns whether the student is HSC
     *
     * @return bool
     */
    public function isHsc(): bool
    {
        return $this->curriculumYearLevel >= 11 && !$this->isIb;
    }


    // Getters


    /**
     * @return bool
     */
    public function isIb(): bool
    {
        return $this->isIb;
    }


    /**
     * @return int
     */
    public function getSbSem(): int
    {
        return $this->sbSem;
    }


    /**
     * @return int
     */
    public function getTerm(): int
    {
        return $this->term;
    }


    /**
     * @return int
     */
    public function getCurriculumYearLevel(): int
    {
        return $this->curriculumYearLevel;
    }


    /**
     * @return int
     */
    public function getCurriculumSemester(): int
    {
        return $this->curriculumSemester;
    }
}
