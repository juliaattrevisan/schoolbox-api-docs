<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

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
    private $term;

    /** @var int */
    private $curriculumYearLevel;

    /** @var int */
    private $curriculumSemester;

    /** @var Alaress_Date_Format */
    private $reportStart;

    /** @var Alaress_Date_Format */
    private $reportFinish;


    /**
     * @param User        $user
     * @param string|null $ibFlag
     * @param string|null $nowOverride Enable now() simulation for testing
     * @throws InvalidArgumentException
     */
    public function __construct(
        User $user,
        ?string $ibFlag = null,
        ?string $nowOverride = null
    ) {
        $now = $nowOverride
            ? new Alaress_Date_Format(\strtotime($nowOverride), Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();

        $this->yearLevel           = $this->deriveUserYearLevel($user);
        $this->isIb                = (bool)$ibFlag; // If unknown (NULL), assume FALSE
        $this->term                = $this->deriveTerm($now);
        $this->curriculumYearLevel = ($this->yearLevel === 11 && $this->term === 4) ? 12 : $this->yearLevel;
        $this->curriculumSemester  = $this->deriveCurriculumSemester($this->yearLevel, $this->term);
        $this->reportStart         = $this->deriveReportStartDate($this->yearLevel, $this->term, $now);
        $this->reportFinish        = $this->deriveReportFinishDate($this->yearLevel, $this->term, $now);
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
        if (!\is_numeric($userYear)) {
            throw new InvalidArgumentException("User must have a numeric year. Found '{$userYear}'");
        }

        return (int)$userYear;
    }


    /**
     * Returns the term we are currently in.
     *
     * @param Alaress_Date_Format $now
     * @return int
     * @throws InvalidArgumentException
     */
    private function deriveTerm(Alaress_Date_Format $now): int
    {
        // Current Term + Semester
        $nowYear = $now->getUsingFormat('Y');
        $firstDayTerm2 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM2_RELATIVE . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm3 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM3_RELATIVE . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm4 = new Alaress_Date_Format(\strtotime(self::FIRST_DAY_TERM4_RELATIVE . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
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
     * @param int $term
     * @return int
     */
    private function deriveCurriculumSemester(int $userYearLevel, int $term): int
    {
        // Apply SB to Curriculum mapping
        if (($userYearLevel === 11 && $term === 4) || ($userYearLevel === 12 && $term === 1)) {
            return 1;
        }
        if ($userYearLevel === 12 && $term === 2) {
            return 2;
        }
        return \in_array($term, [1, 2], true) ? 1 : 2;
    }


    /**
     * Returns whether the student is MYP (IB Middle Years Programme)
     *
     * @return bool
     */
    public function isMyp(): bool
    {
        return $this->yearLevel >= 7 && $this->yearLevel <= 10;
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
     * Derive the date that the report logically starts at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @param Alaress_Date_Format $now
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveReportStartDate(int $yearLevel, int $term, Alaress_Date_Format $now): Alaress_Date_Format
    {
        $nowYear = (int)$now->getUsingFormat('Y');

        if ($yearLevel === 11 && $term === 4) {
            $start = self::FIRST_DAY_TERM4_RELATIVE . " $nowYear";
        } elseif ($yearLevel === 12) {
            if ($term === 1) {
                $nowYear--;
                $start = self::FIRST_DAY_TERM4_RELATIVE . " $nowYear";
            } else {
                $start = self::FIRST_DAY_TERM2_RELATIVE . " $nowYear";
            }
        } elseif (\in_array($term, [1, 2], true)) {
            $start = self::FIRST_DAY_TERM1_RELATIVE . " $nowYear";
        } else {
            // Terms 3+4
            $start = self::FIRST_DAY_TERM3_RELATIVE . " $nowYear";
        }

        return new Alaress_Date_Format(\strtotime($start), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Derive the date that the report logically finishes at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @param Alaress_Date_Format $now
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveReportFinishDate(int $yearLevel, int $term, Alaress_Date_Format $now): Alaress_Date_Format
    {
        $isYear11 = $yearLevel === 11;
        $isYear12 = $yearLevel === 12;
        $nowYear  = (int)$now->getUsingFormat('Y');

        if ($isYear11 && $term === 3) {
            $end = self::FIRST_DAY_TERM4_RELATIVE . " $nowYear";
        } elseif ($isYear11 && $term === 4) {
            $nowYear++;
            $end = self::FIRST_DAY_TERM2_RELATIVE  . " $nowYear";
        } elseif ($isYear12 && $term === 1) {
            $end = self::FIRST_DAY_TERM2_RELATIVE . " $nowYear";
        } elseif (($isYear12 && $term === 2 )|| \in_array($term, [3, 4], true)) {
            $nowYear++;
            $end = self::FIRST_DAY_TERM1_RELATIVE . " $nowYear";
        } else {
            $end = self::FIRST_DAY_TERM3_RELATIVE . " $nowYear";
        }

        return (new Alaress_Date_Format(\strtotime($end), Alaress_Date_Format::TIMESTAMP))->addDays(-1)->endOfDay();
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

    /**
     * Returns the date that the report logically starts at.
     *
     * @return Alaress_Date_Format
     */
    public function getReportStart(): Alaress_Date_Format
    {
        return $this->reportStart;
    }

    /**
     * Returns the date that the report logically finishes at.
     *
     * @return Alaress_Date_Format
     */
    public function getReportFinish(): Alaress_Date_Format
    {
        return $this->reportFinish;
    }
}
