<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress_Date_Format;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Encapsulates relevant student attributes to help determine which report components are relevant
 */
class StudentAttributes
{

    // Hardcoded term dates for now
    private const FIRST_DAY_TERM1_RELATIVE = '1 January';
    private const FIRST_DAY_TERM2_RELATIVE = '1 April';
    private const FIRST_DAY_TERM3_RELATIVE = '1 July';
    private const FIRST_DAY_TERM4_RELATIVE = '1 October';

    /** @var int */
    private $yearLevel;

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
     * @param User                     $user
     * @param string|null              $termOverride
     * @param string|null              $ibFlag
     * @param string|null              $ibFlagOverride
     * @param Alaress_Date_Format|null $now Enable now() simulation for unit tests
     * @throws InvalidArgumentException
     */
    public function __construct(
        User $user,
        ?string $termOverride,
        ?string $ibFlag,
        ?string $ibFlagOverride,
        Alaress_Date_Format $now = null
    ) {
        if ($termOverride && $now) {
            throw new InvalidArgumentException('You may simulate a term or now, but now both');
        }

        $this->yearLevel           = $this->deriveUserYearLevel($user);
        $this->isIb                = (bool)($ibFlagOverride ?? $ibFlag);
        $this->term                = $this->deriveTerm($termOverride, $now ?? Alaress_Date_Format::now());
        $this->sbSem               = \in_array($this->term, [1, 2], true) ? 1 : 2;
        $this->curriculumYearLevel = ($this->yearLevel === 11 && $this->term === 4) ? 12 : $this->yearLevel;
        $this->curriculumSemester  = $this->deriveCurriculumSemester($this->yearLevel, $this->sbSem, $this->term);
    }


    /**
     * @param User $user
     * @return int
     * @throws InvalidArgumentException
     */
    private function deriveUserYearLevel(User $user): int
    {
        $userYear = $user->getYear();
        if ('' === $userYear || null === $userYear) {
            throw new InvalidArgumentException("User must have a year level set");
        }
        return (int)$userYear;
    }


    /**
     * Returns the term we are currently in.
     *
     * @param string|null         $termOverride
     * @param Alaress_Date_Format $now
     * @return int
     * @throws InvalidArgumentException
     */
    private function deriveTerm(?string $termOverride, Alaress_Date_Format $now): int
    {
        // Was a term override provided?
        if (null !== $termOverride && \preg_match('/^[1234]$/', $termOverride)) {
            return (int)$termOverride;
        }

        // Current Term + Semester
        $firstDayTerm2 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM2_RELATIVE), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm3 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM3_RELATIVE), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm4 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM4_RELATIVE), Alaress_Date_Format::TIMESTAMP);
        if ($now->lt($firstDayTerm2)) {
            return 1;
        }
        if ($now->lt($firstDayTerm3)) {
            return 2;
        }
        if ($now->lt($firstDayTerm4)) {
            return 3;
        }
        return 4;
    }


    /**
     * Returns the student's Curriculum Semester
     *
     * @param int $userYearLevel
     * @param int $sbSem
     * @param int $term
     * @return int
     */
    private function deriveCurriculumSemester(int $userYearLevel, int $sbSem, int $term): int
    {
        // Apply SB to Curriculum mapping
        if (($userYearLevel === 11 && $term === 4) || ($userYearLevel === 12 && $term === 1)) {
            return 1;
        }
        if ($userYearLevel === 12 && $term === 2) {
            return 2;
        }
        return $sbSem;
    }


    /**
     * Returns whether the student is MYP (IB Middle Years Programme)
     *
     * @return bool
     */
    public function isMyp(): bool
    {
        return $this->yearLevel <= 10;
    }


    /**
     * Returns whether the student is DP (IB Diploma Programme)
     *
     * @return bool
     */
    public function isDp(): bool
    {
        return $this->yearLevel >= 11 && $this->isIb;
    }


    /**
     * Returns whether the student is HSC
     *
     * @return bool
     */
    public function isHsc(): bool
    {
        return $this->yearLevel >= 11 && !$this->isIb;
    }


    /**
     * Returns the date that the report logically starts at.
     *
     * @return Alaress_Date_Format
     * @throws \InvalidArgumentException
     */
    public function getReportStartDate(): Alaress_Date_Format
    {
        if ($this->yearLevel === 11 && $this->term === 4) {
            $reportStart = self::FIRST_DAY_TERM4_RELATIVE;
        } elseif ($this->yearLevel === 12) {
            if ($this->term === 1) {
                $reportStart = self::FIRST_DAY_TERM4_RELATIVE . (date('Y') - 1);
            } else {
                $reportStart = self::FIRST_DAY_TERM2_RELATIVE;
            }
        } elseif (\in_array($this->term, [1, 2], true)) {
            $reportStart = self::FIRST_DAY_TERM1_RELATIVE;
        } else {
            // Terms 3+4
            $reportStart = self::FIRST_DAY_TERM3_RELATIVE;
        }

        return new Alaress_Date_Format(\strtotime($reportStart), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Returns the date that the report logically ends at.
     *
     * @return Alaress_Date_Format
     * @throws \InvalidArgumentException
     */
    public function getReportFinishDate(): Alaress_Date_Format
    {
        $isYear11 = $this->yearLevel === 11;
        $isYear12 = $this->yearLevel === 12;

        if ($isYear11 && $this->term === 3) {
            $reportEnd = self::FIRST_DAY_TERM4_RELATIVE;
        } elseif ($isYear11 && $this->term === 4) {
            $reportEnd = self::FIRST_DAY_TERM2_RELATIVE . (date('Y') + 1);
        } elseif ($isYear12 && $this->term === 1) {
            $reportEnd = self::FIRST_DAY_TERM2_RELATIVE;
        } elseif ($isYear12 && $this->term === 2) {
            $reportEnd = self::FIRST_DAY_TERM1_RELATIVE . (date('Y') + 1);
        } elseif (\in_array($this->term, [1, 2], true)) {
            $reportEnd = self::FIRST_DAY_TERM3_RELATIVE;
        } else {
            // Terms 3+4
            $reportEnd = self::FIRST_DAY_TERM1_RELATIVE . (date('Y') + 1);
        }

        return (new Alaress_Date_Format(\strtotime($reportEnd), Alaress_Date_Format::TIMESTAMP))->addDays(-1);
    }



    // Getters


    /**
     * @return int
     */
    public function getYearLevel(): int
    {
        return $this->yearLevel;
    }


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
