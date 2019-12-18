<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Alaress_Date_Format;
use function in_array;
use function is_numeric;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use InvalidArgumentException;
use function strtotime;

/**
 * Encapsulates relevant student attributes to help determine which report components are relevant
 *
 * Business rules / assumptions:
 * * Only term 4 will ever wrap around the end of the calendar year
 * * A student's year level is updated in Synergetic on the first day of Term 1
 * * The UPS is running nightly, so we can assume the Schoolbox user year level matches the synergetic value
 * * When generating a historical report, we assume students always graduate to the next year's curriculum
 * * The year 7-10 curriculum semesters 1 and 2 run for terms 1/2 and 3/4, respectively
 * * The year 11 curriculum semester 1 runs for terms 1/2, semester 2 runs for term 3 only
 * * The year 12 curriculum semester 1 runs from the previous year's term 4 into term 1
 * * The year 12 curriculum semester 2 runs for terms 2/3/4
 * * Technically nothing should be due in Yr 12 Term 4, but we include it in Sem2 so the report will continue to show at this time.
 *
 * In the client data, Year12 students are soft-deleted at the end of year 12, and stay as year 12 in the DB.
 * In our test data, we don't delete students, and keep incrementing their year levels so we don't need to keep updating the test data.
 * A request for the report of either scenario should 404.
 */
class StudentAttributes
{
    // Reports are not relevant if student's year level is outside this range
    public const YEAR_LEVEL_MIN = 7;
    public const YEAR_LEVEL_MAX = 12;

    /**
     * The student's year level at the time of the report
     *
     * @var int
     */
    private $yearLevel;

    /** @var bool */
    private $isIb;

    /** @var int */
    private $term;

    /** @var int */
    private $reportDateYear;

    /**
     * What yearLevel curriculum is/was the student performing.
     *
     * eg a Year11 student in Term 4 is performing the Year12 curriculum
     *
     * @var int
     */
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

    /** @var bool */
    private $isHistorical;

    /** @var Alaress_Date_Format */
    private $reportDate;


    /**
     * @param IConfigLoader $config
     * @param User          $user
     * @param string|null   $ibFlag
     * @param string|null   $reportDateOverride
     * @param string|null   $nowOverride            Overrides system time to enable consistent unit tests around deriving a user's year level.
     *                                              eg $reportYearLevel = $currentYearLevel - $nowYear + $reportYear
     * @throws InvalidArgumentException
     */
    public function __construct(
        IConfigLoader $config,
        User $user,
        ?string $ibFlag = null,
        ?string $reportDateOverride = null,
        ?string $nowOverride = null
    ) {
        $reportDate = $reportDateOverride
            ? new Alaress_Date_Format(strtotime($reportDateOverride), Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();
        $now        = $nowOverride
            ? new Alaress_Date_Format(strtotime($nowOverride), Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();

        // No business need to produce future reports, so limit the logic paths.
        if ($reportDate->gt($now)) {
            throw new InvalidArgumentException('Cannot generate a report for the future.');
        }

        $this->validateTermDates($config);
        $this->validateUser($user);

        // If unknown (NULL), assume $ibFlag is FALSE
        $this->isIb           = (bool)$ibFlag;
        $this->reportDateYear = (int)$reportDate->getYear();
        $this->isHistorical   = $reportDate->lt($now);
        $this->reportDate     = $reportDate;

        $this->calculateDerivedAttributes($config, (int)$user->getYear(), $reportDate, $now);
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getReportDate(): Alaress_Date_Format
    {
        return $this->reportDate;
    }


    /**
     * Returns the student's explanation of grades fragment
     *
     * @return string
     */
    public function getExplanationOfGradesType(): string
    {
        if ($this->isMyp()) {
            return 'MYP Year 7-10';
        }
        return "Year {$this->getYearLevel()} " . ($this->isDp() ? 'DP' : 'HSC');
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
        return $this->reportDateYear;
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


    /**
     * @return bool
     */
    public function isCurrent(): bool
    {
        return !$this->isHistorical;
    }

    /**
     * Returns whether this is for a historical report, as opposed to a current report
     *
     * @return bool
     */
    public function isHistorical(): bool
    {
        return $this->isHistorical;
    }


    /**
     * Ensure that term dates are set, and we can construct an ADF from them
     *
     * @param IConfigLoader $config
     * @throws InvalidArgumentException
     */
    private function validateTermDates(IConfigLoader $config): void
    {
        $termConfigs = [
            Config::_TIGS_START_TERM_1,
            Config::_TIGS_START_TERM_2,
            Config::_TIGS_START_TERM_3,
            Config::_TIGS_START_TERM_4,
        ];
        foreach ($termConfigs as $configName) {
            $firstDayOfTerm = $config->get($configName);
            if ($firstDayOfTerm === null || empty(trim($firstDayOfTerm))) {
                throw new InvalidArgumentException("Term start date not set for config '$configName'");
            }
            new Alaress_Date_Format(strtotime($firstDayOfTerm), Alaress_Date_Format::TIMESTAMP);
        }
    }


    /**
     * Ensure user is/was not deleted at time of report and has a supported year level
     *
     * @param User $user
     * @throws InvalidArgumentException
     */
    private function validateUser(User $user): void
    {
        // No business need to produce reports for deleted users
        // This avoids needing to consider deletedAt date when producing historical reports
        if ($user->isDeleted()) {
            throw new InvalidArgumentException("User was deleted at '{$user->getDeletedAt()->getDatabase()}'");
        }

        // Year level must be between
        $userYear = $user->getYear();
        if ('' === $userYear || null === $userYear) {
            throw new InvalidArgumentException("User must have a year level set");
        }
        if (!is_numeric($userYear)) {
            throw new InvalidArgumentException("User must have a numeric year. Found '{$userYear}'");
        }
        if ($userYear < self::YEAR_LEVEL_MIN) {
            throw new InvalidArgumentException(sprintf(
                "User year must be greater than %d Found '%s'",
                self::YEAR_LEVEL_MIN,
                $userYear
            ));
        }
    }


    /**
     * Calculate derived values
     *
     * @param IConfigLoader       $config
     * @param int                 $currentYearLevel
     * @param Alaress_Date_Format $reportDate
     * @param Alaress_Date_Format $now
     * @throws InvalidArgumentException
     */
    private function calculateDerivedAttributes(
        IConfigLoader $config,
        int $currentYearLevel,
        Alaress_Date_Format $reportDate,
        Alaress_Date_Format $now
    ): void {
        $this->yearLevel                = $this->deriveUserYearLevel($config, $currentYearLevel, $reportDate, $now);
        $this->term                     = $this->deriveTerm($config, $reportDate);
        $this->curriculumYearLevel      = $this->deriveCurriculumYearLevel($this->yearLevel, $this->term);
        $this->curriculumSemester       = $this->deriveCurriculumSemester($this->yearLevel, $this->term);
        $this->curriculumSemesterStart  = $this->deriveCurriculumSemesterStartDate($config, $reportDate, $this->yearLevel, $this->term);
        $this->curriculumSemesterFinish = $this->deriveCurriculumSemesterFinishDate($config, $reportDate, $this->yearLevel, $this->term);
        $this->curriculumYearStart      = $this->deriveCurriculumYearStartDate($config, $reportDate, $this->yearLevel, $this->term);
        $this->curriculumYearFinish     = $this->deriveCurriculumYearFinishDate($config, $reportDate, $this->yearLevel, $this->term);
    }


    /**
     * Derive the date that the student's curriculum semester logically starts at.
     *
     * @param IConfigLoader       $config
     * @param Alaress_Date_Format $reportDate
     * @param int                 $yearLevel Student's year level at time of report, eg 11
     * @param int                 $term      The report's term, eg 3
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumSemesterStartDate(
        IConfigLoader $config,
        Alaress_Date_Format $reportDate,
        int $yearLevel,
        int $term
    ): Alaress_Date_Format {
        $reportYear = (int)$reportDate->getYear();

        // Is report for Term 4 from previous year?
        if ($reportDate->lt($this->getYearLevelIncrementDate($config, $reportYear))) {
            $reportYear--;
        }

        if ($yearLevel === 11 && $term === 4) {
            $start = $config->get(Config::_TIGS_START_TERM_4) . " $reportYear";
        } elseif ($yearLevel === 12) {
            if ($term === 1) {
                $reportYear--;
                $start = $config->get(Config::_TIGS_START_TERM_4) . " $reportYear";
            } else {
                $start = $config->get(Config::_TIGS_START_TERM_2) . " $reportYear";
            }
        } elseif (in_array($term, [1, 2], true)) {
            $start = $config->get(Config::_TIGS_START_TERM_1) . " $reportYear";
        } else {
            // Terms 3+4
            $start = $config->get(Config::_TIGS_START_TERM_3) . " $reportYear";
        }

        return $this->buildDate($start);
    }


    /**
     * Derive the date that the student's curriculum semester logically finishes at.
     *
     * @param IConfigLoader       $config
     * @param Alaress_Date_Format $reportDate
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumSemesterFinishDate(
        IConfigLoader $config,
        Alaress_Date_Format $reportDate,
        int $yearLevel,
        int $term
    ): Alaress_Date_Format {
        $isYear11       = $yearLevel === 11;
        $isYear12       = $yearLevel === 12;
        $reportDateYear = (int)$reportDate->getYear();

        // Is report for Term 4 from previous year?
        if ($reportDate->lt($this->getYearLevelIncrementDate($config, $reportDateYear))) {
            $reportDateYear--;
        }

        if ($isYear11 && $term === 3) {
            $end = $config->get(Config::_TIGS_START_TERM_4) . " $reportDateYear";
        } elseif ($isYear11 && $term === 4) {
            $reportDateYear++;
            $end = $config->get(Config::_TIGS_START_TERM_2) . " $reportDateYear";
        } elseif ($isYear12 && $term === 1) {
            $end = $config->get(Config::_TIGS_START_TERM_2) . " $reportDateYear";
        } elseif (($isYear12 && $term === 2) || in_array($term, [3, 4], true)) {
            $reportDateYear++;
            $end = $config->get(Config::_TIGS_START_TERM_1) . " $reportDateYear";
        } else {
            $end = $config->get(Config::_TIGS_START_TERM_3) . " $reportDateYear";
        }

        return (new Alaress_Date_Format(strtotime($end), Alaress_Date_Format::TIMESTAMP))->addDays(-1)->endOfDay();
    }


    /**
     * Derive the date that the student's curriculum year logically starts at.
     *
     * @param IConfigLoader       $config
     * @param Alaress_Date_Format $reportDate
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumYearStartDate(
        IConfigLoader $config,
        Alaress_Date_Format $reportDate,
        int $yearLevel,
        int $term
    ): Alaress_Date_Format {
        $reportDateYear = (int)$reportDate->getYear();

        // Is report for Term 4 from previous year?
        if ($reportDate->lt($this->getYearLevelIncrementDate($config, $reportDateYear))) {
            $reportDateYear--;
        }

        if ($yearLevel >= 7 && $yearLevel <= 10) {
            $start = $config->get(Config::_TIGS_START_TERM_1) . " $reportDateYear";
        } elseif ($yearLevel === 11) {
            if ($term !== 4) {
                $start = $config->get(Config::_TIGS_START_TERM_1) . " $reportDateYear";
            } else {
                $start = $config->get(Config::_TIGS_START_TERM_4) . " $reportDateYear";
            }
        } else {
            $reportDateYear--;
            $start = $config->get(Config::_TIGS_START_TERM_4) . " $reportDateYear";
        }

        return new Alaress_Date_Format(strtotime($start), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Derive the date that the student's curriculum year logically finishes at.
     *
     * @param IConfigLoader       $config
     * @param Alaress_Date_Format $reportDate
     * @param int                 $yearLevel
     * @param int                 $term
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function deriveCurriculumYearFinishDate(
        IConfigLoader $config,
        Alaress_Date_Format $reportDate,
        int $yearLevel,
        int $term
    ): Alaress_Date_Format {
        $reportDateYear = (int)$reportDate->getYear();

        // Is report for Term 4 from previous year?
        if ($reportDate->lt($this->getYearLevelIncrementDate($config, $reportDateYear))) {
            $reportDateYear--;
        }

        if ($yearLevel === 11) {
            if (4 !== $term) {
                $end = $config->get(Config::_TIGS_START_TERM_4) . " $reportDateYear";
            } else {
                $reportDateYear += 2;
                $end            = $config->get(Config::_TIGS_START_TERM_1) . " $reportDateYear";
            }
        } else {
            $reportDateYear++;
            $end = $config->get(Config::_TIGS_START_TERM_1) . " $reportDateYear";
        }

        return (new Alaress_Date_Format(strtotime($end), Alaress_Date_Format::TIMESTAMP))->addDays(-1)->endOfDay();
    }


    /**
     * Return's the user's year level at the time of the report
     *
     * @param IConfigLoader       $config
     * @param int                 $currentYearLevel
     * @param Alaress_Date_Format $reportDate
     * @param Alaress_Date_Format $now
     * @return int
     * @throws InvalidArgumentException
     */
    private function deriveUserYearLevel(IConfigLoader $config, int $currentYearLevel, Alaress_Date_Format $reportDate, Alaress_Date_Format $now): int
    {
        // Assume student graduated every calendar year
        $reportYearLevel = $currentYearLevel + $reportDate->getYear() - $now->getYear();

        // Is reportDate and nowDate on different sides of the yearLevel change boundary?
        $isReportPriorToYearChange = $reportDate->lt($this->getYearLevelIncrementDate($config, (int)$reportDate->getYear()));
        $isNowPriorToYearChange    = $now->lt($this->getYearLevelIncrementDate($config, (int)$now->getYear()));

        if ($isReportPriorToYearChange && !$isNowPriorToYearChange) {
            // Need to undo the year level increment
            $reportYearLevel--;
        } elseif (!$isReportPriorToYearChange && $isNowPriorToYearChange) {
            // Need to simulate the year level increment
            $reportYearLevel++;
        }

        // Are we trying to generate a report for too far in the past?
        if ($reportYearLevel < self::YEAR_LEVEL_MIN) {
            throw new InvalidArgumentException("Cannot generate a report for student with derived year level: $reportYearLevel");
        }

        return $reportYearLevel;
    }


    /**
     * Returns the date when a student's yearLevel was incremented
     *
     * @param IConfigLoader $config
     * @param int           $year
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function getYearLevelIncrementDate(IConfigLoader $config, int $year): Alaress_Date_Format
    {
        // Student year levels are incremented on the first day of Term 1
        return $this->buildDate($config->get(Config::_TIGS_START_TERM_1) . " {$year}");
    }


    /**
     * Returns an ADF object from a human-readable date string
     *
     * @param string $date
     * @return Alaress_Date_Format
     * @throws InvalidArgumentException
     */
    private function buildDate(string $date): Alaress_Date_Format
    {
        return new Alaress_Date_Format(strtotime($date), Alaress_Date_Format::TIMESTAMP);
    }


    /**
     * Returns the term that the specified date falls in
     *
     * Assumes that term 4 is the only term that will ever wrap around the end of year
     *
     * @param IConfigLoader       $config
     * @param Alaress_Date_Format $date
     * @return int
     * @throws InvalidArgumentException
     */
    private function deriveTerm(IConfigLoader $config, Alaress_Date_Format $date): int
    {
        $year = (int)$date->getUsingFormat('Y');

        if ($date->lt($this->buildDate($config->get(Config::_TIGS_START_TERM_1) . " $year"))) {
            // We're in term 4 of the previous year
            return 4;
        }
        if ($date->lt($this->buildDate($config->get(Config::_TIGS_START_TERM_2) . " $year"))) {
            return 1;
        }
        if ($date->lt($this->buildDate($config->get(Config::_TIGS_START_TERM_3) . " $year"))) {
            return 2;
        }
        if ($date->lt($this->buildDate($config->get(Config::_TIGS_START_TERM_4) . " $year"))) {
            return 3;
        }
        // We're in term 4 of this year
        return 4;
    }


    /**
     * Returns a Curriculum Year the specified yearLevel and term
     *
     * @param int $yearLevel
     * @param int $term
     * @return int
     */
    private function deriveCurriculumYearLevel(int $yearLevel, int $term): int
    {
        return ($yearLevel === 11 && $term === 4) ? 12 : $yearLevel;
    }


    /**
     * Returns the student's Curriculum Semester
     *
     * @param int $yearLevel
     * @param int $term
     * @return int
     */
    private function deriveCurriculumSemester(int $yearLevel, int $term): int
    {
        // Special case: Year12 Sem1 curriculum runs from Term4 of prior year to Term1
        if (($yearLevel === 11 && $term === 4) || ($yearLevel === 12 && $term === 1)) {
            return 1;
        }
        // Special case: Year12 Sem2 starts in Term2
        if ($yearLevel === 12 && $term === 2) {
            return 2;
        }
        // Normal case: Terms 1,2 => Sem1, Terms 3,4 => Sem2
        return in_array($term, [1, 2], true) ? 1 : 2;
    }
}
