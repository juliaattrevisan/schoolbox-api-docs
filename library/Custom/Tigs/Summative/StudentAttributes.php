<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Alaress_Date_Format;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Encapsulates relevant student attributes to help determine which report components are relevant
 */
class StudentAttributes
{

    /** @var string */
    private $firstDateTerm1Relative;

    /** @var string */
    private $firstDateTerm2Relative;

    /** @var string */
    private $firstDateTerm3Relative;

    /** @var string */
    private $firstDateTerm4Relative;

    /** @var int */
    private $yearLevel;

    /** @var bool */
    private $isIb;

    /** @var int */
    private $term;

    /** @var int */
    private $nowYear;

    /** @var int */
    private $curriculumYearLevel;

    /**
     * The semester ordinal, either 1 or 2
     *
     * @var int
     */
    private $curriculumSemester;

    /** @var Alaress_Date_Format */
    private $curriculumSemesterStart;

    /** @var Alaress_Date_Format */
    private $curriculumSemesterFinish;

    /** @var Alaress_Date_Format */
    private $curriculumYearStart;

    /** @var Alaress_Date_Format */
    private $curriculumYearFinish;


    /**
     * @param IConfigLoader $config
     * @param User          $user
     * @param string|null   $ibFlag
     * @param string|null   $nowOverride Enable now() simulation for testing
     * @throws InvalidArgumentException
     */
    public function __construct(
        IConfigLoader $config,
        User $user,
        ?string $ibFlag = null,
        ?string $nowOverride = null
    ) {
        $now = $nowOverride
            ? new Alaress_Date_Format(\strtotime($nowOverride), Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();

        $this->firstDateTerm1Relative   = $config->get(Config::_TIGS_START_TERM_1);
        $this->firstDateTerm2Relative   = $config->get(Config::_TIGS_START_TERM_2);
        $this->firstDateTerm3Relative   = $config->get(Config::_TIGS_START_TERM_3);
        $this->firstDateTerm4Relative   = $config->get(Config::_TIGS_START_TERM_4);
        $this->yearLevel                = $this->deriveUserYearLevel($user);
        $this->isIb                     = (bool)$ibFlag; // If unknown (NULL), assume FALSE
        $this->nowYear                  = (int)$now->getUsingFormat('Y');
        $this->term                     = $this->deriveTerm($now);
        $this->curriculumYearLevel      = ($this->yearLevel === 11 && $this->term === 4) ? 12 : $this->yearLevel;
        $this->curriculumSemester       = $this->deriveCurriculumSemester($this->yearLevel, $this->term);
        $this->curriculumSemesterStart  = $this->deriveCurriculumSemesterStartDate($this->yearLevel, $this->term);
        $this->curriculumSemesterFinish = $this->deriveCurriculumSemesterFinishDate($this->yearLevel, $this->term);
        $this->curriculumYearStart      = $this->deriveCurriculumYearStartDate($this->yearLevel, $this->term);
        $this->curriculumYearFinish     = $this->deriveCurriculumYearFinishDate($this->yearLevel, $this->term);
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
        $nowYear = $this->nowYear;
        $firstDayTerm2 = new Alaress_Date_Format(\strtotime($this->firstDateTerm2Relative . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm3 = new Alaress_Date_Format(\strtotime($this->firstDateTerm3Relative . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
        $firstDayTerm4 = new Alaress_Date_Format(\strtotime($this->firstDateTerm4Relative . " $nowYear"), Alaress_Date_Format::TIMESTAMP);
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
     * Derive the date that the student's curriculum semester logically starts at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumSemesterStartDate(int $yearLevel, int $term): Alaress_Date_Format
    {
        $nowYear = $this->nowYear;

        if ($yearLevel === 11 && $term === 4) {
            $start = $this->firstDateTerm4Relative . " $nowYear";
        } elseif ($yearLevel === 12) {
            if ($term === 1) {
                $nowYear--;
                $start = $this->firstDateTerm4Relative . " $nowYear";
            } else {
                $start = $this->firstDateTerm2Relative . " $nowYear";
            }
        } elseif (\in_array($term, [1, 2], true)) {
            $start = $this->firstDateTerm1Relative . " $nowYear";
        } else {
            // Terms 3+4
            $start = $this->firstDateTerm3Relative . " $nowYear";
        }

        return new Alaress_Date_Format(\strtotime($start), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Derive the date that the student's curriculum semester logically finishes at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumSemesterFinishDate(int $yearLevel, int $term): Alaress_Date_Format
    {
        $isYear11 = $yearLevel === 11;
        $isYear12 = $yearLevel === 12;
        $nowYear  = $this->nowYear;

        if ($isYear11 && $term === 3) {
            $end = $this->firstDateTerm4Relative . " $nowYear";
        } elseif ($isYear11 && $term === 4) {
            $nowYear++;
            $end = $this->firstDateTerm2Relative  . " $nowYear";
        } elseif ($isYear12 && $term === 1) {
            $end = $this->firstDateTerm2Relative . " $nowYear";
        } elseif (($isYear12 && $term === 2 )|| \in_array($term, [3, 4], true)) {
            $nowYear++;
            $end = $this->firstDateTerm1Relative . " $nowYear";
        } else {
            $end = $this->firstDateTerm3Relative . " $nowYear";
        }

        return (new Alaress_Date_Format(\strtotime($end), Alaress_Date_Format::TIMESTAMP))->addDays(-1)->endOfDay();
    }


    /**
     * Derive the date that the student's curriculum year logically starts at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumYearStartDate(int $yearLevel, int $term): Alaress_Date_Format
    {
        $nowYear = $this->nowYear;

        if ($yearLevel >= 7 && $yearLevel <= 10) {
            $start = $this->firstDateTerm1Relative . " $nowYear";
        } elseif ($yearLevel === 11) {
            if ($term !== 4) {
                $start = $this->firstDateTerm1Relative . " $nowYear";
            } else {
                $start = $this->firstDateTerm4Relative . " $nowYear";
            }
        } else {
            $nowYear--;
            $start = $this->firstDateTerm4Relative . " $nowYear";
        }

        return new Alaress_Date_Format(\strtotime($start), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Derive the date that the student's curriculum year logically finishes at.
     *
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumYearFinishDate(int $yearLevel, int $term): Alaress_Date_Format
    {
        $nowYear = $this->nowYear;

        if ($yearLevel === 11) {
            if (4 !== $term) {
                $end = $this->firstDateTerm4Relative . " $nowYear";
            } else {
                $nowYear += 2;
                $end = $this->firstDateTerm1Relative . " $nowYear";
            }
        } else {
            $nowYear++;
            $end = $this->firstDateTerm1Relative . " $nowYear";
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
    public function getCalendarYear(): int
    {
        return $this->nowYear;
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
     * Returns the date that the Subject Report logically starts at.
     *
     * @return Alaress_Date_Format
     */
    public function getCurriculumSemesterStart(): Alaress_Date_Format
    {
        return $this->curriculumSemesterStart;
    }


    /**
     * Returns the date that the Subject Report logically finishes at.
     *
     * @return Alaress_Date_Format
     */
    public function getCurriculumSemesterFinish(): Alaress_Date_Format
    {
        return $this->curriculumSemesterFinish;
    }


    /**
     * Returns the date that the Participation Report logically starts at.
     *
     * @return Alaress_Date_Format
     */
    public function getCurriculumYearStart(): Alaress_Date_Format
    {
        return $this->curriculumYearStart;
    }


    /**
     * Returns the date that the Participation Report logically finishes at.
     *
     * @return Alaress_Date_Format
     */
    public function getCurriculumYearFinish(): Alaress_Date_Format
    {
        return $this->curriculumYearFinish;
    }

    /**
     * Returns two semesters with start and finish dates
     * @return Semester[]
     */
    public function getCurriculumSemesters(): array
    {
        if ($this->getCurriculumSemester() === 1) {
            $semesters[1]['start']  = $this->getCurriculumSemesterStart();
            $semesters[1]['finish'] = $this->getCurriculumSemesterFinish();
            $semesters[2]['start']  = (clone $this->getCurriculumSemesterFinish())->addDays(1)->startOfDay();
            $semesters[2]['finish'] = $this->getCurriculumYearFinish();
        } else {
            $semesters[1]['start']  = $this->getCurriculumYearStart();
            $semesters[1]['finish'] = (clone $this->getCurriculumSemesterStart())->addDays(-1)->endOfDay();
            $semesters[2]['start']  = $this->getCurriculumSemesterStart();
            $semesters[2]['finish'] = $this->getCurriculumSemesterFinish();
        }
        return [
            1 => new Semester(1, $semesters[1]['start'], $semesters[1]['finish']),
            2 => new Semester(2, $semesters[2]['start'], $semesters[2]['finish']),
        ];
    }
}
