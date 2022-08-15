<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IAssessableComparator;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Count;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\StandardDeviation;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\SortUsersByName;
use Schoolbox\User;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;

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
     * Students that are a part of this markbook.
     *
     * @var Collection
     */
    private $students;

    /**
     * Assessments part of this markbook on the top level (not including assessments inside projects)
     *
     * @var Collection|IAssessable[]
     */
    protected $topLevelAssessments;

    /**
     * The mark scheme to use when summarising a student's overall grade for these assessments.
     *
     * @var IMarkScheme
     */
    protected $defaultMarkScheme;

    /**
     * The user viewing the markbook, usually whoever is logged in
     *
     * @var User
     */
    protected $viewingUser;

    /**
     * @var int
     */
    private $totalWeight = 0;

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
     * Whether draft exists for student & assessment combination
     *
     * key: assessment ID, student ID
     * @var array
     */
    private $hasDraft = [];

    /**
     * Build a markbook out of a list of assessments.
     *
     * This assumes that all participants, responses and feedbacks have been filled out.
     *
     * @param GenericCollection|IAssessable[] $assessments
     * @param IMarkScheme              $defaultMarkScheme
     * @param User                     $viewingUser
     */
    public function __construct(GenericCollection $assessments, IMarkScheme $defaultMarkScheme, User $viewingUser)
    {
        $this->assessments       = $assessments;
        $this->defaultMarkScheme = $defaultMarkScheme;
        $this->viewingUser       = $viewingUser;
        $this->topLevelAssessments = $assessments->filter(function (IAssessable $assessment) {
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
     * @return Collection
     */
    public function getStudents(): Collection
    {
        if (!isset($this->students)) {
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
            $this->students = $participants;
        }
        return $this->students;
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
        $weightedSummator  = new WeightedAverage();

        // Not all assessables are used in stats:
        // only ones for which feedback is visible, and are not project sub-assessments, are usable.
        $visibleAssessments = $this->topLevelAssessments->filter(function (IAssessable $assessable) {
            return $assessable->isFeedbackVisibleToUser($this->viewingUser);
        });

        $visibleAssessmentIds = $visibleAssessments->getPropertyValues('id');
        foreach ($this->getStudents() as $student) {
            // Check whether latest feedback is draft
            $statsHasDraft = false;
            foreach ($this->assessments as $assessment) {
                if (!$assessment instanceof DueWork) {
                    continue;
                }
                $participant = $assessment->getParticipantIfExtant($student);
                if (!$participant || $participant->isExcluded()) {
                    continue;
                }
                $latestFeedback = $participant->findMostRecentInstructorGivenFeedback();
                if ($latestFeedback && $latestFeedback->isDraft()) {
                    $this->hasDraft[$assessment->getId()][$student->getId()] = true;
                    if (in_array($assessment->getId(), $visibleAssessmentIds)) {
                        $statsHasDraft = true;
                    }
                }
            }

            $this->studentStatistics[$student->getId()]['weightedMean'] =
                $this->defaultMarkScheme->getHumanReadableMark(
                    $weightedSummator->summateAllAssessablesForUser($visibleAssessments, $student, true)
                );
            $this->studentStatistics[$student->getId()]['meanNormalised'] =
                    $weightedSummator->summate($visibleAssessments, $student, true);

            $this->studentStatistics[$student->getId()]['draftWeightedMean'] = null;
            $this->studentStatistics[$student->getId()]['meanDraftNormalised'] = null;
            if ($statsHasDraft) {
                $this->studentStatistics[$student->getId()]['draftWeightedMean'] =
                    $this->defaultMarkScheme->getHumanReadableMark(
                        $weightedSummator->summateAllAssessablesForUser($visibleAssessments, $student, false)
                    );
                $this->studentStatistics[$student->getId()]['meanDraftNormalised'] =
                    $weightedSummator->summate($visibleAssessments, $student, false);
            }
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
        $this->weightedMean = $this->defaultMarkScheme->getHumanReadableMark($this->normalisedMean);
    }

    /**
     * @return array
     */
    public function getHasDraft(): array
    {
        return $this->hasDraft;
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
        $draftNormMarks = [];

        // Only collate stats for assessments whose feedback may be viewed by this user
        if ($assessment->isFeedbackVisibleToUser($this->viewingUser)) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $normMarks[]      = $participant->getNormalisedMark(true);
                $draftNormMarks[] = $participant->getNormalisedMark(false);
            }
        }

        $fivePoints = (new Summary($normMarks))->getSummary();

        $statistics = [
            'mean'              => null,
            'sd'                => null,
            'min'               => $markScheme->getHumanReadableMark($minSummator->summateList($normMarks)),
            'minNormalized'     => $fivePoints['min'],
            'max'               => $markScheme->getHumanReadableMark($maxSummator->summateList($normMarks)),
            'maxNormalized'     => $fivePoints['max'],
            'count'             => $countSummator->summateList($normMarks),
            'quartile1'         => $fivePoints['quartile1'],
            'quartile2'         => $fivePoints['quartile2'],
            'quartile3'         => $fivePoints['quartile3']
        ];
        if ($assessment->getContext() instanceof Project) {
            $statistics['weightInProject'] = $assessment->getWeight();
        } else {
            $statistics['weight'] = $assessment->getWeight();
        }

        // mean and SD are only meaningful for quantitative marks
        if ($assessment->getMarkScheme()->isQuantitative()) {
            $statistics['mean']             = $markScheme->getHumanReadableMark($meanSummator->summateList($normMarks));
            $statistics['draftMean']        = $markScheme->getHumanReadableMark($meanSummator->summateList($draftNormMarks));
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
     * Build student ranking
     * See also similar function in library/Schoolbox/Assessment/Course/Markbook/CourseMarkbook.php.
     * @return array (integer ranking indexed by studentId)
     */
    public function getRanking(): array
    {
        // First build basic array (id,grade).
        $rankList = [];
        foreach ($this->studentStatistics as $studentId => $studentStatistic) {
            if ($studentStatistic['meanNormalised'] !== null) {
                $rankList[] = [
                    'id'     => $studentId,
                    'grade' => $studentStatistic['meanNormalised'],
                ];
            }
        }

        // Now sort based on grade.
        usort($rankList, function ($one, $two) {
            return $two['grade'] <=> $one['grade'];
        });

        $rankValue            = 1;
        $currentRank          = 1;
        $prevStudent['grade'] = '';

        /**
         * iterate over ordered list of ranked students, and actually give them helpful rankings
         * not 42.06, but 5 (you are 5th in the class)
         */
        $rankByStudentId = [];
        foreach ($rankList as $student) {
            if ($prevStudent['grade'] !== $student['grade']) {
                $rankValue = $currentRank;
            }
            $rankByStudentId[$student['id']] = $rankValue;
            $currentRank++;
            $prevStudent = $student;
        }

        return $rankByStudentId;
    }
}
