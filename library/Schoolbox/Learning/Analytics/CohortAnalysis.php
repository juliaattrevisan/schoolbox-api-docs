<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Analytics;

use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\LearningAnalytics\Results\TaskList;
use Schoolbox\LearningAnalytics\Results\TaskResults;
use Schoolbox\LearningAnalytics\Rubrics\AllRubrics;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\User;

/**
 * Class CohortAnalysis
 *
 * Object that bundles up all the learning analysis you might want to do against a cohort.
 *
 * A COHORT is defined as: a set of participants involved in any unit imported from any one course specification at a specific point in time.
 *
 *
 * @package Schoolbox\Learning\Analytics
 */
class CohortAnalysis
{
    /** @var Specification\Model */
    private $courseSpecification;

    /** @var Folder\Model */
    private $classFolder;

    /** @var Term\Model */
    private $term;

    /** @var Collection|Implementation\Unit\Model[] */
    private $unitImplementations;

    /** @var array */
    private $completionData = [];

    /**
     * User grade averages. Key is the user object, data value is a float representing their grade
     *
     * @var array
     */
    private $gradeAverages = [];


    /**
     * You need to have FULLY hydrated and linked up your units.
     * This class depends on all unit, assessment, participation, response and feedback references being fully accounted for.
     *
     * @param Specification\Model                    $courseSpecification the course we're analysing
     * @param Collection|Implementation\Unit\Model[] $unitImplementations ALL the unit implementations pertaining to the given specification
     * @param Folder\Model                           $classFolder         if given, the individual class to limit participation displayed
     * @param Term\Model                             $term                if given, the time bounds within which to limit participation displayed
     */
    public function __construct(Specification\Model $courseSpecification, Collection $unitImplementations, Folder\Model $classFolder = null, Term\Model $term = null)
    {
        $this->courseSpecification = $courseSpecification;
        $this->unitImplementations = $unitImplementations;
        $this->unitSpecifications  = $unitImplementations->map(function (Implementation\Unit\Model $unitImpl) { return $unitImpl->getSpecification(); })->indexBy('id');
        $this->classFolder         = $classFolder;
        $this->term                = $term;
    }


    /**
     * @return array
     */
    private function getCompletionData()
    {
        // cache this badboy
        if (empty($this->completionData)) {

            $specActivityComplete = [];
            $participantProgress  = [];

            foreach ($this->unitImplementations as $unitImpl) {

                $unitSpec   = $unitImpl->getSpecification();
                $unitSpecId = $unitSpec->getId();

                foreach ($unitImpl->getAssessables() as $implActivity) {

                    $specActivity   = $implActivity->getSpecificationAssessment();
                    $specActivityId = $specActivity->getId();

                    if (empty($specActivityComplete[$unitSpecId][$specActivityId])) {
                        // assume complete until told otherwise
                        $specActivityComplete[$unitSpecId][$specActivityId] = true;
                    }

                    foreach ($implActivity->getAllParticipants() as $participant) {

                        $completed = $participant->getAllResponses()->filter(function (IResponse $response) { return $response->isSubmitted(); })->count() > 0;

                        $participantProgress[$unitSpecId][$participant->getUser()->getId()][$specActivityId] = $completed;

                        if (!$completed) {
                            // if ANY participant who maps onto a SPEC activity has NO responses, that activity is considered not completed overall.
                            $specActivityComplete[$unitSpecId][$specActivityId] = false;
                        }

                    }

                }

                $this->completionData[$unitSpecId] = [
                    'unitSpec'            => $unitSpec,
                    'activityCompletions' => $specActivityComplete[$unitSpecId],
                    'participantProgress' => $participantProgress[$unitSpecId]
                ];
            }

        }

        return $this->completionData;
    }


    /**
     * @param array $activityCompletions
     * @param array $participantProgress
     * @return array
     */
    private function reduceCompletion(array $activityCompletions, array $participantProgress)
    {
        return [
            'mostAdvancedStudent' => 100 * max(array_map(function (array $progress) { return array_sum($progress) / count($progress); }, $participantProgress)),
            'classCompletions'    => 100 * array_sum($activityCompletions) / count($activityCompletions),
        ];
    }

    /**
     * Returns:
     *   - the progress of the furthest-completed student, and
     *   - the proportion of activities completed by ALL participants in unit IMPLEMENTATIONS
     * indexed by unit SPECIFICATION.
     *
     * @return array
     */
    public function getProgressByUnit()
    {
        $progress = [];

        foreach ($this->getCompletionData() as $unitSpecId => $unitCompletionData) {

            $specActivityComplete = $unitCompletionData['activityCompletions'];
            $participantProgress  = $unitCompletionData['participantProgress'];

            $progress[$unitSpecId] = $this->reduceCompletion($specActivityComplete, $participantProgress);
            $progress[$unitSpecId]['unit'] = $unitCompletionData['unitSpec'];
        }

        return $progress;
    }


    /**
     * This is a flattening of getProgressByUnit()
     *
     * Returns two data: as percentages;
     *   - the progress of the furthest-completed participant (fewest activities remaining)
     *   - proportion of activities completed by ALL participants
     *
     * @return array
     */
    public function getOverallProgress()
    {
        $activityCompletions = [];
        $participantProgress = [];

        // flatten the unit specs out of the data, accommodating students who exist in more than one unit
        foreach ($this->getCompletionData() as $unitSpecId => $unitCompletionData) {

            foreach ($unitCompletionData['activityCompletions'] as $specActivityId => $activityCompletion) {
                if (!array_key_exists($specActivityId, $activityCompletions)) {
                    $activityCompletions[$specActivityId] = true;
                }
                $activityCompletions[$specActivityId] = min($activityCompletions[$specActivityId], $activityCompletion);
            }

            foreach ($unitCompletionData['participantProgress'] as $participantId => $progress) {
                foreach ($progress as $specActivityId => $isComplete) {
                    if (empty($participantProgress[$participantId][$specActivityId])) {
                        $participantProgress[$participantId][$specActivityId] = false;
                    }
                    $participantProgress[$participantId][$specActivityId] = max($participantProgress[$participantId][$specActivityId], $isComplete);
                }
            }
        }

        return $this->reduceCompletion($activityCompletions, $participantProgress);
    }


    /**
     * Returns the IDs and grades of all participants WHO HAVE FEEDBACK DATA in this analysis, indexed by user ID and sorted descending by computed grades.
     * Activities with weight of zero or marktype none are excluded from analysis.
     *
     * @param int $sort
     * @return \float[]
     */
    public function getCohortRankings($sort = SORT_DESC)
    {
        // kaching
        if (empty($this->gradeAverages)) {

            $summator = new WeightedAverage();

            /** @var IAssessable[] $activities */
            $activities = [];

            /** @var User[] $participants */
            $users = [];

            foreach ($this->unitImplementations as $unitImplementation) {
                // participants can exist in units simultaneously, so we need to flatten according to specActivity before summating.
                foreach ($unitImplementation->getAssessables() as $implActivity) {

                    $specActivityId = $implActivity->getSpecificationAssessment()->getId();

                    if (empty($activities[$specActivityId])) {
                        // only want one of these
                        $activities[$specActivityId] = clone $implActivity->getSpecificationAssessment();
                    }

                    $specActivity = $activities[$specActivityId];

                    foreach ($implActivity->getAllParticipants() as $implParticipant) {

                        if (!$specActivity->isParticipant($implParticipant->getUser())) {
                            // dupe the implParticipant object, linked to the specActivity
                            $specActivity->getAllParticipants()->add(new Participant(
                                $implParticipant->getUser(), $specActivity
                            ));

                            // collate deduped list of users
                            $users[$implParticipant->getUser()->getId()] = $implParticipant->getUser();
                        }

                        $specParticipant = $specActivity->getParticipant($implParticipant->getUser());

                        // load in the most recent published feedback if any exists - on collision, take the highest mark
                        $implFeedback = $implParticipant->findMostRecentPublishedFeedback();

                        if (!$implFeedback) {
                            // no feedback: move onto the next participant
                            continue;
                        }

                        if ($specParticipant->hasPublishedFeedback()) {
                            // account for collision; keep the highest mark
                            if ($specParticipant->findMostRecentPublishedFeedback()->getNormalisedMark() < $implFeedback->getNormalisedMark()) {
                                // replace
                                $specFeedback = clone $implFeedback;
                                $specFeedback->setAssessable($specActivity);
                                $specParticipant->setAllFeedbacks(new GenericCollection($specFeedback, [$specFeedback]));
                                $specActivity->getFeedbacks()->add($specFeedback);
                            }

                        } else {
                            // drop that feedback straight in
                            $specFeedback = clone $implFeedback;
                            $specFeedback->setAssessable($specActivity);
                            $specParticipant->setAllFeedbacks(new GenericCollection($specFeedback, [$specFeedback]));
                            $specActivity->getFeedbacks()->add($specFeedback);
                        }
                    }

                }
            }

            $activities = new Collection($activities);

            foreach ($users as $userId => $user) {
                $this->gradeAverages[$userId] = [
                    'user'    => $user,
                    'average' => $summator->summateAllAssessablesForUser($activities, $user, true)
                ];
            }
        }

        $dir = $sort == SORT_ASC ? 1 : -1;

        uasort($this->gradeAverages, function ($r1, $r2) use ($dir) {
            return ($r1['average'] - $r2['average']) * $dir;
        });

        return $this->gradeAverages;
    }


    /**
     * Returns five-point grade data from unit IMPLEMENTATIONS for all participants, indexed by unit SPECIFICATION activity
     * Applicable to the activity box-and-whisker graph
     *
     * @param $registry
     * @return IFeedback[][]
     */
    public function getCohortGradesByActivity($registry)
    {
        // shim around old code
        return (new TaskResults($registry))->getResults();
    }


    /**
     * @param $registry
     * @param $metadata
     * @return Collection|Collection[]|IAssessable[][]
     */
    public function getActivities($registry, $metadata)
    {
        // shim around old code
        return (new TaskList($registry, $metadata))->getActivities();
    }


    /**
     * Returns five-point grade data from unit implementations for all participants, indexed by rubric indicator
     * Applicable to the rubric box-and-whisker graph
     *
     * (Cuts the same data as getCohortGradesByActivity() but by indicators instead of activity.)
     *
     * @param $registry
     * @param $metadata
     * @return array
     */
    public function getCohortGradesByIndicator($registry, $metadata)
    {
        // exploit old class
        return (new AllRubrics($registry, $metadata))->getRubricData();
    }


    /**
     * Returns all participants in the cohort
     *
     * @return array
     */
    public function getEffort()
    {
        $cohort = $this->getCohortRankings(SORT_ASC);

        if ($this->classFolder) {
            $folderIds = $this->classFolder->getId();
        } else {
            $folderIds = array_unique($this->unitImplementations->map(function (Implementation\Unit\Model $unitImpl) { return $unitImpl->getFolder()->getId(); })->getAll());
        }

        /** @var Collection|Collection[]|Folder\Log\Model[][] $accessLogs */
        $accessLogs = Folder\Log\Mapper::getInstance()->findByColumnList([
            'user' => array_keys($cohort),
            'cid'  => $folderIds
        ])->groupByCallback(function (Folder\Log\Model $log) {
            return $log->getUser()->getId();
        });

        $tally = [];

        foreach ($cohort as $userId => $userData) {
            $tally[$userId] = $userData;

            $tally[$userId]['hits'] = empty($accessLogs[$userId])
                ? 0
                : array_sum($accessLogs[$userId]->getColumnValues('hitcount'));
        }

        return $tally;
    }

}
