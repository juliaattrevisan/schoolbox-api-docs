<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IAssessableComparator;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Count;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\StandardDeviation;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\SortUsersByName;
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
     * Assessments part of this markbook on the top level (not including assessments inside projects)
     *
     * @var Collection|IAssessable[]
     */
    protected $topLevelAssessments;

    /**
     * The grade group to use when summarising a student's overall grade for these assessments.
     *
     * @var GradeGroup
     */
    protected $gradeGroup;

    /**
     * The user viewing the markbook, usually whoever is logged in
     *
     * @var User
     */
    protected $viewingUser;

    /**
     * @var int
     */
    private $totalWeight;

    /**
     * Normalised (0.0 - 100.0) average mark for all assessments in this markbook
     *
     * @var float
     */
    private $normalisedMean;

    /**
     * Human readable weighted average mark for all assessments in this markbook
     *
     * @var string
     */
    private $weightedMean;

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
     * value: an array of statistics (currently: weightedMean)
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
     * @param User                     $viewingUser
     */
    public function __construct(Collection $assessments, GradeGroup $gradeGroup, User $viewingUser)
    {
        $this->assessments = $assessments;
        $this->gradeGroup  = $gradeGroup;
        $this->viewingUser = $viewingUser;
        $this->topLevelAssessments = $assessments->filter(function(IAssessable $assessment) {
            return !$assessment->getContext() instanceof Project;
        });

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
     * Get all top level assessments in this collection.
     *
     * @return Collection|IAssessable[]
     */
    public function getTopLevelAssessments()
    {
        return $this->topLevelAssessments;
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
                if (!$participants->offsetExists($student->getId())) {
                    $participants->setOffset($student->getId(), $student);
                }
            }
        }
        $participants->sortByCallback((new SortUsersByName())->getCallable(), true);
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
     * Collates statistics for each assessment, student and the overall markbook.
     */
    protected function collateStatistics()
    {
        // Collate all assessment statistics
        foreach ($this->assessments as $assessment) {
            $this->collateAssessmentStatistics($assessment);
        }

        // But only top-level assessments contribute to markbook total weight
        foreach ($this->getTopLevelAssessments() as $assessment) {
            $this->totalWeight += $assessment->getWeight();
        }

        // Collate weight percent for each top-level assessment
        if ($this->totalWeight > 0) {
            foreach ($this->getTopLevelAssessments() as $assessment) {
                $assessmentId = $assessment->getId();
                $this->assessmentStatistics[$assessmentId]['weightPercent'] =
                    $this->assessmentStatistics[$assessmentId]['weight'] / $this->totalWeight * 100;
            }
        }

        // Summate student's statistics as an average, weighted by each assessment's weight.
        // Render as whatever the default grade group is
        $overallMarkScheme = new LetterGrade($this->gradeGroup);
        $weightedSummator  = new WeightedAverage();

        // Not all assessables are used in stats:
        // only ones for which feedback is visible, and are not project sub-assessments, are usable.
        $visibleAssessments = $this->topLevelAssessments->filter(function(IAssessable $assessable) {
            return $assessable->isFeedbackVisibleToUser($this->viewingUser);
        });

        foreach ($this->getStudents() as $student) {
            $this->studentStatistics[$student->getId()]['weightedMean'] =
                $overallMarkScheme->getHumanReadableMark(
                    $weightedSummator->summateAllAssessablesForUser($visibleAssessments, $student, true)
                );
            $this->studentStatistics[$student->getId()]['meanNormalised'] =
                    $weightedSummator->summate($visibleAssessments, $student, true);
        }

        // Summate overall statistics:
        // First, average each assessable for all participants
        // Then, summate the result as an average, weighted by each assessment's weight.
        // Render as whatever the default grade group is

        $this->normalisedMean = $weightedSummator->summateAllAssessables(
            $visibleAssessments,
            true,
            new Average()
        );
        $this->weightedMean = $overallMarkScheme->getHumanReadableMark($this->normalisedMean);
    }


    /**
     * @return float
     */
    public function getNormalisedMean()
    {
        return $this->normalisedMean;
    }


    /**
     * @return string
     */
    public function getWeightedMean()
    {
        return $this->weightedMean;
    }

    /**
     * Collates statistics for a single assessment.
     *
     * @param IAssessable $assessment
     */
    protected function collateAssessmentStatistics(IAssessable $assessment)
    {
        $meanSummator  = new Average();
        $stdevSummator = new StandardDeviation();
        $minSummator   = new Minimum();
        $maxSummator   = new Maximum();
        $countSummator = new Count();

        // Summate assessment statistics for all users for each assessment
        // Render all stats as percentages for now?
        $markScheme = new Percentage();

        // Just get marks for all students, and let the summators do the rest.
        $normMarks = [];

        // Only collate stats for assessments whose feedback may be viewed by this user
        if ($assessment->isFeedbackVisibleToUser($this->viewingUser)) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $normMarks[] = $participant->getNormalisedMark(true);
            }
        }

        // Exclude null marks
        $marks = array_values(array_filter($normMarks));
        sort($marks);
        $quartile2 = $this->findMedian($marks);
        $countAllMarks = count($marks);
        if (isset($marks) && !empty($marks)) {
            if ($countAllMarks > 1) {
                $quartile1 = $this->findMedian($this->setupFirstQuartile($marks, $quartile2));
                $quartile3 = $this->findMedian($this->setupThirdQuartile($marks, $quartile2));
            } else {
                $quartile1 = $marks[0] / 2;
                $quartile3 = $marks[0] / 2;
            }
        } else {
            $quartile1 = null;
            $quartile3 = null;
        }

        $statistics = [
            'mean'              => null,
            'sd'                => null,
            'min'               => $markScheme->getHumanReadableMark($minSummator->summateList($normMarks)),
            'minNormalized'     => $minSummator->summateList($normMarks),
            'max'               => $markScheme->getHumanReadableMark($maxSummator->summateList($normMarks)),
            'maxNormalized'     => $maxSummator->summateList($normMarks),
            'count'             => $countSummator->summateList($normMarks),
            'quartile1'         => $quartile1,
            'quartile3'         => $quartile3,
        ];
        if ($assessment->getContext() instanceof Project) {
            $statistics['weightInProject'] = $assessment->getWeight();
        } else {
            $statistics['weight'] = $assessment->getWeight();

        }

        // mean and SD are only meaningful for quantitative marks
        if ($assessment->getMarkScheme()->isQuantitative()) {
            $statistics['mean']             = $markScheme->getHumanReadableMark($meanSummator->summateList($normMarks));
            $statistics['meanNormalized'] = $quartile2;
            $statistics['sd']               = $markScheme->getHumanReadableMark($stdevSummator->summateList($normMarks));
            $statistics['sdNormalized']     = $stdevSummator->summateList($normMarks);
        }

        // Collate project statistics too
        if ($assessment instanceof Project) {
            $statistics['projectTotalWeight'] = 0;
            foreach ($assessment->getAssessables() as $projectAssessment) {
                $this->collateAssessmentStatistics($projectAssessment);
                $statistics['projectTotalWeight'] += $projectAssessment->getWeight();
            }

            // Pass 2: weight percent
            if ($statistics['projectTotalWeight'] > 0) {
                foreach ($assessment->getAssessables() as $projectAssessment) {
                    $projectAssessmentId = $projectAssessment->getId();
                    $this->assessmentStatistics[$projectAssessmentId]['projectWeightPercent']
                        = $this->assessmentStatistics[$projectAssessmentId]['weightInProject'] / $statistics['projectTotalWeight'] * 100;
                }
            }
        }

        $this->assessmentStatistics[$assessment->getId()] = $statistics;
    }

    /**
     * Retrieves a generator which iterates all assessments, and drills down into project
     * sub-assessments.
     *
     * @return \Generator|IAssessable[]
     */
    public function getAssessmentsWithProjectContents()
    {
        foreach ($this->getTopLevelAssessments() as $assessment) {

            yield $assessment;

            // If this is a project - loop through assessments inside it
            if ($assessment instanceof Project) {
                foreach ($assessment->getAssessables() as $projectAssessment) {
                    yield $projectAssessment;
                }
            }
        }
    }


    /**
     * Sorts the markbook's assessments. If no comparator is passed, applies default markbook sorting.
     *
     * @param IAssessableComparator|null $comparator
     * @return $this
     */
    public function sortAssessables(IAssessableComparator $comparator = null): self
    {
        if (!$comparator) {
            $comparator = new SortForMarkbook();
        }
        $this->assessments->sortByCallback($comparator->getCallable());
        return $this;
    }

    /**
     * Find the values used to calculate first quartile. The
     * 2nd quartile is used to calculate the 1st quartile
     *
     * @param array $values
     * @param $quartile2
     * @return array
     */
    public function setupFirstQuartile(array $values, $quartile2)
    {
        $firstHalve = array_slice($values, 0, floor(count($values) / 2));
        if (count($firstHalve) > 1) {
            $firstHalve[] = $quartile2;
        }
        return $firstHalve;
    }

    /**
     * Find the values used to calculate third quartile - The
     * 2nd quartile is used to calculate the 3rd quartile
     *
     * @param array $values
     * @param $quartile2
     * @return array
     */
    public function setupThirdQuartile(array $values, $quartile2)
    {
        $even = !(count($values) % 2);

        if ($even) {
            return array_slice($values, floor(count($values) / 2), count($values));
        }

        $secondHalve = array_slice($values, ceil(count($values) / 2), count($values));
        if (count($secondHalve) > 1) {
            array_unshift($secondHalve, $quartile2);
        }
        return $secondHalve;
    }

    /**
     * Calculates medians
     *
     * @param array $values
     * @return float
     */
    function findMedian(array $values)
    {
        $median = null;
        /* Figure out medians in different ways depending
         * on even or odd number of elements
         */
        $halfway = floor(count($values) / 2);

        $even = !(count($values) % 2);

        /* Careful of these offsets */
        if ($even) {
            $median = ($values[$halfway - 1] + $values[$halfway]) / 2.0;
        } else {
            $median = $values[$halfway];
        }

        return (float)$median;
    }
}
