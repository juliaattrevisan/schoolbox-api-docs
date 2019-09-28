<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress_Date_Format;
use InvalidArgumentException;
use function array_key_exists;
use function strtotime;

/**
 * Responsible for determining a Canvas CourseSection's logical open/due dates
 * to map to a Schoolbox UnitImpl open/due date.
 */
class CourseSectionDates
{

    /**
     * A relative date offset to apply to a UnitImpl dueDate to derive an openDate if one
     * cannot be derived from any explicit datum. eg '-1 year'
     *
     * @var string
     */
    private $defaultUnitImplLength;

    /** @var string[] */
    private $startOfDayCache = [];

    /** @var string[] */
    private $endOfDayCache = [];


    /**
     * It appears that in some cases, there is no explicit datum we can use to derive a UnitImpl.openDate.
     *
     * In this case, we will just set it to some time before the due date. This is not critical, as the
     * UnitImpl.OpenDate is used for just rendering the component sections as open/due on the homepage component.
     *
     * @param string $defaultUnitImplOffset
     * @throws InvalidArgumentException
     */
    public function __construct(string $defaultUnitImplOffset = '-1 year')
    {
        if (strtotime($defaultUnitImplOffset) === false) {
            throw new InvalidArgumentException("Unable to parse date offset: $defaultUnitImplOffset");
        }
        $this->defaultUnitImplLength = $defaultUnitImplOffset;
    }


    /**
     * Returns a pair of dates suitable to apply to a Schoolbox UnitImpl from Canvas data
     *
     * Order of preference:
     * * CourseSection start/end dates
     * * CourseSection.EnrollmentTerm start/end dates
     * * CourseSection.Course start/conclude dates
     * * CourseSection.Course.EnrollmentTerm start/end dates
     *
     * @param string|null $sectionStartAt
     * @param string|null $sectionEndAt
     * @param string|null $sectionTermDateStart
     * @param string|null $sectionTermDateEnd
     * @param string|null $courseStartAt
     * @param string|null $courseConcludeAt
     * @param string|null $courseTermDateStart
     * @param string|null $courseTermDateEnd
     * @return string[]
     */
    public function unitImplDates(
        ?string $sectionStartAt,
        ?string $sectionEndAt,
        ?string $sectionTermDateStart,
        ?string $sectionTermDateEnd,
        ?string $courseStartAt,
        ?string $courseConcludeAt,
        ?string $courseTermDateStart,
        ?string $courseTermDateEnd
    ): array {

        $dueDate  = $sectionEndAt ?? $sectionTermDateEnd ?? $courseConcludeAt ?? $courseTermDateEnd;
        $openDate = $sectionStartAt ?? $sectionTermDateStart ?? $courseStartAt ?? $courseTermDateStart;

        if ($openDate === null) {
            $openDate = strtotime($this->defaultUnitImplLength, $dueDate);
        }
        // Schoolbox UnitImpl.open/dueDate hydration forces date to start/end of day
        if (!array_key_exists($openDate, $this->startOfDayCache)) {
            $this->startOfDayCache[$openDate] = Alaress_Date_Format::newDatabaseDate($openDate)->startOfDay()->getDatabase();
        }
        if (!array_key_exists($dueDate, $this->endOfDayCache)) {
            $this->endOfDayCache[$dueDate] = Alaress_Date_Format::newDatabaseDate($dueDate)->endOfDay()->getDatabase();
        }
        return [$this->startOfDayCache[$openDate], $this->endOfDayCache[$dueDate]];
    }
}
