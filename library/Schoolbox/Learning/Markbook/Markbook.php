<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Collection;
use Schoolbox\Config\Config;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Count;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\StandardDeviation;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\MarkScheme\AbstractMarkScheme;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\User;

/**
 * Class Markbook
 */
class Markbook
{
    /**
     * The assessments that are a part of this markbook.
     *
     * @var Collection|IAssessable[]
     */
    protected $assessments;

    /**
     * Statistics for each assessment.
     *
     * key:   assessment ID
     * value: an array of statistics:
     *        * average
     *        * sd
     *        * min
     *        * max
     *
     * @var array
     */
    protected $assessmentStatistics = [];

    /**
     * Statistics for each student.
     *
     * key:   student ID
     * value: an array of statistics:
     *        * average
     *        * sd
     *        * min
     *        * max
     *
     * @var array
     */
    protected $studentStatistics = [];

    /**
     * Build a markbook out of a list of assessments.
     *
     * This assumes that all participants, responses and feedbacks have been filled out.
     *
     * @param Collection|IAssessable[] $assessments
     */
    public function __construct(Collection $assessments)
    {
        $this->assessments = $assessments;
        $this->collateStatistics();
    }

    /**
     * Get all assessments in this collection.
     *
     * @return Collection|IAssessable[]
     */
    public function getAssessments()
    {
        return $this->assessments;
    }

    /**
     * Retrieves all students who have participated in any of the assessments in the markbook.
     *
     * @return Collection|User[]
     */
    public function getStudents()
    {
        $participants = new Collection();
        foreach ($this->getAssessments() as $assessment) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $student = $participant->getUser();
                $participants[$student->getId()] = $student;
            }
        }
        // Sort by name?
        return $participants;
    }

    /**
     * Gets all stats for the assessments in this markbook.
     *
     * @return array
     */
    public function getAssessmentStatistics()
    {
        return $this->assessmentStatistics;
    }

    /**
     * Gets all stats for the students in this markbook.
     *
     * @return array
     */
    public function getStudentStatistics()
    {
        return $this->studentStatistics;
    }

    /**
     * Collates statistics for each assessment into an array.
     */
    protected function collateStatistics()
    {
        $meanSummator     = new Average();
        $stdevSummator    = new StandardDeviation();
        $minSummator      = new Minimum();
        $maxSummator      = new Maximum();
        $weightedSummator = new WeightedAverage();
        $countSummator    = new Count();
        foreach ($this->getAssessments() as $assessment) {

            // Summator for assessment statistics:
            // a relative of ISummator which takes an assessment instead of a user
            // The summator will get all normalized marks out, then convert the outputs to mean/min/max/sd
            // Render as whatever type of mark scheme this is?

            $markScheme = $assessment->getMarkScheme();

            // Special case for quiz total mark:
            // fall back to a different summator
            // TODO figure out a better way to do this
            if ($markScheme instanceof TotalMarkFractional) {
                $markScheme = new TotalMarkWholeNumber($markScheme->getTotalMark());
            }

            $statistics = [
                'mean'  => null,
                'sd'    => null,
                'min'   => $markScheme->getHumanReadableMark($minSummator->summateSingleAssessable($assessment, true)),
                'max'   => $markScheme->getHumanReadableMark($maxSummator->summateSingleAssessable($assessment, true)),
                'count' => $countSummator->summateSingleAssessable($assessment, true),
            ];

            // mean and SD are only meaningful for quantitative marks
            if ($markScheme->isQuantitative()) {
                $statistics['mean'] = $markScheme->getHumanReadableMark($meanSummator->summateSingleAssessable($assessment, true));
                $statistics['sd']   = $markScheme->getHumanReadableMark($stdevSummator->summateSingleAssessable($assessment, true));
            }

            $this->assessmentStatistics[$assessment->getId()] = $statistics;
        }

        // Summator for a student's statistics:
        // weighted average.
        // The average is all we care about for student stats
        // Render as a grade scheme
//        $this->studentStatistics = array_map([$this, 'calculateStatistics'], $studentMarks);

        // Inject whatever the default grade group is
        // TODO inject all of this
        $defaultGradeGroup = \Config::get('default_grade_group');
        $overallMarkScheme = new LetterGrade(
            GradeGroupMapper::getInstance()->findById($defaultGradeGroup)
        );
        foreach ($this->getStudents() as $student) {
            $this->studentStatistics[$student->getId()]['mean'] =
                $overallMarkScheme->getHumanReadableMark(
                    $weightedSummator->summate($this->getAssessments(), $student, true)
                );
        }

//        var_dump(
//            $this->assessmentStatistics,
//            $this->studentStatistics
//        );

        // Summator for overall statistics:
        // relative of weighted average, which takes no user.
        // Again, average is all we care about
    }

    /**
     * Calculates generic statistics for a list of values.
     *
     * @param array $values
     * @return array
     */
    protected function calculateStatistics($values)
    {
        // Possible extra stats (for box plots):
        // median, Q1, Q3, lower and upper fence
        $statistics = [
            'mean'  => null,
            'sd'    => null,
            'min'   => null,
            'max'   => null,
            'count' => count($values),
        ];
        if ($statistics['count'] > 0) {
            // These need to be converted back into human-readable versions
            $statistics['mean'] = array_sum($values) / $statistics['count'];
            $statistics['min']  = min($values);
            $statistics['max']  = max($values);

            if ($statistics['count'] > 1) {
                // Standard deviation only makes sense for a sample size of at least 2
                $variance = 0;
                foreach ($values as $mark) {
                    $variance += pow($mark - $statistics['mean'], 2);
                }
                // Old markbook used population standard deviation, rather than sample
                // To use sample SD: subtract 1 from denominator
                $statistics['sd'] = sqrt($variance / $statistics['count']);
            }
        }
        return $statistics;
    }
}
