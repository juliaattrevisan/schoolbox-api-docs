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
     * A list of assessments to operate upon
     *
     * @var GenericCollection<IAssessable>
     */
    private GenericCollection $assessments;


    /**
     * Students that are a part of this markbook.
     *
     * @var Collection
     */
    private Collection $students;


    /**
     * Assessments part of this markbook on the top level (not including assessments inside projects)
     *
     * @var Collection<IAssessable>
     */
    protected Collection $topLevelAssessments;


    /**
     * @var int
     */
    private int $totalWeight = 0;


    /**
     * Normalised (0.0 - 100.0) average mark for all assessments in this markbook
     *
     * @var null|float
     */
    private ?float $normalisedMean;


    /**
     * Normalised (0.0 - 100.0) average mark for all draft assessments in this markbook
     *
     * @var null|float
     */
    private ?float $draftNormalisedMean;


    /**
     * Human readable weighted average mark for all assessments in this markbook
     *
     * @var null|string
     */
    private ?string $weightedMean;


    /**
     * Human readable weighted average mark for all draft assessments in this markbook
     *
     * @var null|string
     */
    private ?string $draftWeightedMean;


    /**
     * Statistics for each assessment.
     *
     * key:   assessment ID
     * value: an array of statistics (currently: mean, sd, min, max, count)
     *
     * @var array
     */
    protected array $assessmentStatistics = [];


    /**
     * Statistics for each student.
     *
     * key:   student ID
     * value: an array of statistics (currently: weightedMean)
     *
     * @var array
     */
    protected array $studentStatistics = [];


    /**
     * Whether draft exists for student & assessment combination
     *
     * key: assessment ID, student ID
     * @var array
     */
    private array $hasDraft = [];


    /**
     * Build a markbook out of a list of assessments.
     *
     * This assumes that all participants, responses and feedbacks have been filled out.
     *
     *
     * There are three optional reductive filters: $studentIds, $curriculumCodes, $folderIds
     *
     * Any of those could be provided (or not provided) and they work in conjunction with one another.
     * For this Markbook class, these three filtration options operate on $assessments which is typically fulfilled
     * and prehydrated from somewhere else and $assessments could be reused by some other part of the app beyond
     * markbook calculations.
     *
     * The three filters here basically pick-and-choose what parts of $assessments needs to be operated upon without
     * affecting anything else that might want to operate on the full set of $assessments.
     *
     * @param GenericCollection<IAssessable> $assessments The assessments that are a part of this markbook.
     * @param IMarkScheme              $defaultMarkScheme The mark scheme to use when summarising a student's overall grade for these assessments.
     * @param User                     $viewingUser The user viewing the markbook, usually whoever is logged in
     * @param array|null $studentIds A reductive filter that operates on $assessments: a set of user ids
     * @param array|null $curriculumCodes A reductive filter that operates on $assessments: a set of curriculum codes
     * @param array|null $folderIds A reductive filter that operates on $assessments: a set of folder ids
     */
    public function __construct(
        GenericCollection $assessments,
        private IMarkScheme $defaultMarkScheme,
        private User $viewingUser,
        private ?array $studentIds = null,
        private ?array $curriculumCodes = null,
        private ?array $folderIds = null
    ) {
        /* Check if we had a specific set of $curriculumCodes to care about:
         * if we did, and this activity doesn't use one of them, skip it
         */
        $this->assessments = $assessments->filter(function (IAssessable $assessment) {
            return $this->isFilteredCurriculumCode($assessment->getCurriculumNodes()->getPropertyValues('code'));
        });

        /* Find all the Project instances within the set of $assessments that we might already have filtered out
         */
        $this->topLevelAssessments = $this->assessments->filter(function (IAssessable $assessment) {
            return !$assessment->getContext() instanceof Project;
        });

        $this->collateStatistics();
    }


    /**
     * Check if we had a specific set of $studentIds to care about: if we did, and this user isn't one of those
     * people, skip them
     *
     * @param int $studentId
     * @return bool
     */
    private function isFilteredStudent(int $studentId): bool
    {
        /* No specific people to care about? Everyone's included
         */
        if (null === $this->studentIds || !count($this->studentIds)) {
            return true;
        }

        /* We only care about specific people: check that $studentId is one of them
         */
        if (\in_array($studentId, $this->studentIds)) {
            return true;
        }

        /* This person should be skipped
         */
        return false;
    }


    /**
     * Check if we had a specific set of $curriculumCodes to care about: if we did, and this activity doesn't have at
     * least one of them, skip this activity
     *
     * @param array $curriculumCodes
     * @return bool
     */
    private function isFilteredCurriculumCode(array $curriculumCodes): bool
    {
        /* No specific curriculum codes to care about? Everything's included
         */
        if (null === $this->curriculumCodes || !count($this->curriculumCodes)) {
            return true;
        }

        /* We only care about specific activities that share curriculum codes with the ones we're interested in:
         * check for overlaps. If at least one activity curric code matches our curric code filter, we're in
         */
        if (count(array_intersect($curriculumCodes, $this->curriculumCodes))) {
            return true;
        }

        /* This activity should be skipped
         */
        return false;
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

                    /* Check if we had a specific set of $studentIds to care about:
                     * if we did, and this user isn't one of those people, skip them
                     */
                    if (!$this->isFilteredStudent($student->getId())) {
                        continue;
                    }

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
     * Collates statistics for each assessment, student and the overall markbook.
     */
    protected function collateStatistics(): void
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
        $weightedSummator = new WeightedAverage();

        // Not all assessables are used in stats:
        // only ones for which feedback is visible, and are not project sub-assessments, are usable.
        $visibleAssessments = $this->topLevelAssessments->filter(function (IAssessable $assessable) {
            return $assessable->isFeedbackVisibleToUser($this->viewingUser);
        });

        $visibleAssessmentIds = $visibleAssessments->getPropertyValues('id');
        foreach ($this->getStudents() as $student) {
            /* Check if we had a specific set of $studentIds to care about:
             * if we did, and this user isn't one of those people, skip them
             */
            if (!$this->isFilteredStudent($student->getId())) {
                continue;
            }

            // Check whether latest feedback is draft
            $statsHasDraft = false;
            foreach ($this->assessments as $assessment) {
                if ($this->isMostRecentFeedbackDraft($assessment, $student)) {
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

        $this->draftNormalisedMean = $weightedSummator->summateAllAssessables(
            $visibleAssessments,
            false,
            new Average()
        );

        $this->weightedMean = $this->defaultMarkScheme->getHumanReadableMark($this->normalisedMean);
        $this->draftWeightedMean = $this->defaultMarkScheme->getHumanReadableMark($this->draftNormalisedMean);
    }


    /**
     * Is the most recent feedback on `$assessment` a draft?
     * * Due work:   is the most recent feedback for `$student` a draft?
     * * Project:    is any activity's most recent feedback for `$student` is a
     *               draft?
     * * All others: no
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return bool
     */
    private function isMostRecentFeedbackDraft(IAssessable $assessment, User $student): bool
    {
        $participant = $assessment->getParticipantIfExtant($student);
        if (!$participant || $participant->isExcluded()) {
            return false;
        }

        // If a due work:
        if ($assessment instanceof DueWork) {
            $latestFeedback = $participant->findMostRecentInstructorGivenFeedback();
            return $latestFeedback && $latestFeedback->isDraft();
        }

        // If a project: check if any of the activities have a draft
        if ($assessment instanceof Project) {
            foreach ($assessment->getAssessables() as $activity) {
                if ($this->isMostRecentFeedbackDraft($activity, $student)) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Collates statistics for a single assessment.
     *
     * @param IAssessable $assessment
     */
    protected function collateAssessmentStatistics(IAssessable $assessment): void
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
                /* Check if we had a specific set of $studentIds to care about:
                 * if we did, and this user isn't one of those people, skip them
                 *
                 * FIXME Maybe controversial: applying filters here basically reduces the universe. You'll be making
                 *  boxplots for a subset of people and might not be indicative of the entire *actual* class/cohort,
                 *  which might be somewhat misleading unless you think about this mindfully. If your class/cohort had
                 *  20 people in it, but 3 people are in your student filter, is the class average based on:
                 *    - those 20 people, or
                 *    - just those 3 people?
                 */
                if (!$this->isFilteredStudent($participant->getUser()->getId())) {
                    continue;
                }

                $normMarks[]      = $participant->getNormalisedMark(true);
                $draftNormMarks[] = $participant->getNormalisedMark(false);
            }
        }

        $fivePoints = (new Summary($normMarks))->getSummary();

        $statistics = [
            'mean'          => null,
            'sd'            => null,
            'min'           => $markScheme->getHumanReadableMark($minSummator->summateList($normMarks)),
            'minNormalized' => $fivePoints['min'],
            'max'           => $markScheme->getHumanReadableMark($maxSummator->summateList($normMarks)),
            'maxNormalized' => $fivePoints['max'],
            'count'         => $countSummator->summateList($normMarks),
            'quartile1'     => $fivePoints['quartile1'],
            'quartile2'     => $fivePoints['quartile2'],
            'quartile3'     => $fivePoints['quartile3']
        ];

        /* $assessment might or might not be in a Project
         * (Projects are assessments too)
         *
         * If it's in a Project, we need to inspect its siblings' weights to do an additional analysis
         */
        if ($assessment->getContext() instanceof Project) {
            $statistics['weightInProject'] = $assessment->getWeight();

            /* Init at zero
             */
            $statistics['projectTotalWeight'] = 0;

            /* If $assessment is inside a Project, it needs to know about the weights of its siblings so that $assessment
             * can be represented as a proportion of the siblings' combined weights
             *
             * Note that this might be repeated a bit, for every assessment in the project itself but there's probably
             * nothing convenient we can do to address that right now
             */
            foreach ($assessment->getContext()->getAssessables() as $projectAssessment) {
                $statistics['projectTotalWeight'] += $projectAssessment->getWeight();
            }

            /* Avoid a division by zero
             */
            if ($statistics['projectTotalWeight'] > 0) {
                $statistics['projectWeightPercent'] = 100 * $statistics['weightInProject'] / $statistics['projectTotalWeight'];
            }
        } else {
            $statistics['weight'] = $assessment->getWeight();
        }

        // mean and SD are only meaningful for quantitative marks
        if ($assessment->getMarkScheme()->isQuantitative()) {
            $statistics['mean']         = $markScheme->getHumanReadableMark($meanSummator->summateList($normMarks));
            $statistics['draftMean']    = $markScheme->getHumanReadableMark($meanSummator->summateList($draftNormMarks));
            $statistics['sd']           = $markScheme->getHumanReadableMark($stdevSummator->summateList($normMarks));
            $statistics['sdNormalized'] = $stdevSummator->summateList($normMarks);
        }

        $this->assessmentStatistics[$assessment->getId()] = $statistics;
    }


    /**
     * Retrieves a generator which iterates all assessments, and drills down into project
     * sub-assessments.
     *
     * @return \Generator<IAssessable>
     */
    public function getAssessmentsWithProjectContents(): \Generator
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
    public function sortAssessables(?IAssessableComparator $comparator = null): self
    {
        if (!$comparator) {
            $comparator = new SortForMarkbook();
        }
        $this->assessments->sortByCallback($comparator->getCallable());
        return $this;
    }


    /**
     * Build student ranking
     *
     * @see /library/Schoolbox/Assessment/Course/Markbook/CourseMarkbook.php for similar function getRanking().
     * @return array [studentId => integer ranking]
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
        $rank = [];
        foreach ($rankList as $student) {
            if ($prevStudent['grade'] !== $student['grade']) {
                $rankValue = $currentRank;
            }
            $rank[$student['id']] = $rankValue;
            $currentRank++;
            $prevStudent = $student;
        }

        return $rank;
    }


    /* Getters/Setters
     */


    /**
     * Get all assessments in this collection.
     *
     * @return Collection<IAssessable>
     */
    public function getAssessments(): Collection
    {
        return $this->assessments;
    }


    /**
     * Get all top level assessments in this collection.
     *
     * @return Collection<IAssessable>
     */
    public function getTopLevelAssessments(): Collection
    {
        return $this->topLevelAssessments;
    }


    /**
     * Gets all stats for the assessments in this markbook.
     *
     * @return array
     */
    public function getAssessmentStatistics(): array
    {
        return $this->assessmentStatistics;
    }


    /**
     * Gets all stats for the students in this markbook.
     *
     * @return array
     */
    public function getStudentStatistics(): array
    {
        return $this->studentStatistics;
    }


    /**
     * @return array
     */
    public function getHasDraft(): array
    {
        return $this->hasDraft;
    }


    /**
     * @return float|null
     */
    public function getNormalisedMean(): float|null
    {
        return $this->normalisedMean;
    }


    /**
     * @return float|null
     */
    public function getDraftNormalisedMean(): float|null
    {
        return $this->draftNormalisedMean;
    }


    /**
     * @return null|string
     */
    public function getWeightedMean(): ?string
    {
        return $this->weightedMean;
    }


    /**
     * @return null|string
     */
    public function getDraftWeightedMean(): ?string
    {
        return $this->draftWeightedMean;
    }
}
