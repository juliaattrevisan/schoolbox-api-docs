<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Count;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\StandardDeviation;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\Percentage;
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
     * The grade group to use when summarising a student's overall grade for these assessments.
     *
     * @var GradeGroup
     */
    protected $gradeGroup;

    /**
     * Statistics for the whole markbook.
     *
     * Currently only contains a weighted mean.
     *
     * @var array
     */
    protected $markbookStatistics = [];

    /**
     * Statistics for each assessment.
     *
     * key:   assessment ID
     * value: an array of statistics (currently: mean, sd, min, max, count)
     *
     * @var array
     */
    protected $assessmentStatistics = [];

    /**
     * Statistics for each student.
     *
     * key:   student ID
     * value: an array of statistics (currently: mean)
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
     * @param GradeGroup               $gradeGroup
     */
    public function __construct(Collection $assessments, GradeGroup $gradeGroup)
    {
        $this->assessments = $assessments;
        $this->gradeGroup  = $gradeGroup;
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
     * Gets all stats that span the entire markbook.
     *
     * @return array
     */
    public function getMarkbookStatistics()
    {
        return $this->markbookStatistics;
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
     * Collates statistics for each assessment, student and the overall markbook.
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

            // Summate assessment statistics for all users for each assessment
            // Render all stats as percentages for now?
            $markScheme = new Percentage();

            $statistics = [
                'mean'              => null,
                'sd'                => null,
                'min'               => $markScheme->getHumanReadableMark($minSummator->summateSingleAssessable($assessment, true)),
                'minNormalized'     => $minSummator->summateSingleAssessable($assessment, true),
                'max'               => $markScheme->getHumanReadableMark($maxSummator->summateSingleAssessable($assessment, true)),
                'maxNormalized'     => $maxSummator->summateSingleAssessable($assessment, true),
                'count'             => $countSummator->summateSingleAssessable($assessment, true),
            ];

            // mean and SD are only meaningful for quantitative marks
            if ($assessment->getMarkScheme()->isQuantitative()) {
                $statistics['mean']             = $markScheme->getHumanReadableMark($meanSummator->summateSingleAssessable($assessment, true));
                $statistics['meanNormalized']   = $meanSummator->summateSingleAssessable($assessment, true);
                $statistics['sd']               = $markScheme->getHumanReadableMark($stdevSummator->summateSingleAssessable($assessment, true));
                $statistics['sdNormalized']     = $stdevSummator->summateSingleAssessable($assessment, true);
            }

            $this->assessmentStatistics[$assessment->getId()] = $statistics;
        }

        // Summate student's statistics as an average, weighted by each assessment's weight.
        // Render as whatever the default grade group is
        $overallMarkScheme = new LetterGrade($this->gradeGroup);


        foreach ($this->getStudents() as $student) {
            $this->studentStatistics[$student->getId()]['mean'] =
                $overallMarkScheme->getHumanReadableMark(
                    $weightedSummator->summateAllAssessablesForUser($this->getAssessments(), $student, true)
                );
            $this->studentStatistics[$student->getId()]['meanNormalised'] =
                    $weightedSummator->summate($this->getAssessments(), $student, true);
        }

        // Summate overall statistics:
        // First, average each assessable for all participants
        // Then, summate the result as an average, weighted by each assessment's weight.
        // Render as whatever the default grade group is
        $this->markbookStatistics['mean'] = $overallMarkScheme->getHumanReadableMark(
            $weightedSummator->summateAllAssessables(
                $this->getAssessments(),
                true,
                $meanSummator
            )
        );
    }
}
