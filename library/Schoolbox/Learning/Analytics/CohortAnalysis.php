<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Analytics;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\LearningAnalytics\Results\AssessmentList;
use Schoolbox\LearningAnalytics\Results\AssessmentResults;
use Schoolbox\LearningAnalytics\Rubrics\AllRubrics;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Resource\Folder\Log\Model as FolderLog;
use Schoolbox\Resource\Folder\Log\Mapper as FolderLogMapper;

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
    /** @var null|Folder */
    private $classFolder;

    /** @var Collection|UnitImplementation[] */
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
     * @param Collection|UnitImplementation[]  $unitImplementations ALL the unit implementations pertaining to the given specification
     * @param Folder                           $classFolder         if given, the individual class to limit participation displayed
     * @param Term                             $term                the time bounds within which to limit participation displayed
     */
    public function __construct(Collection $unitImplementations, Folder $classFolder = null, Term $term)
    {
        $this->unitImplementations = $unitImplementations;
        $this->unitSpecifications  = $unitImplementations->map(function (UnitImplementation $unitImpl) { return $unitImpl->getSpecification(); })->indexBy('id');
        $this->classFolder         = $classFolder;
    }


    /**
     * @return array
     */
    private function getCompletionData()
    {
        // cache this badboy
        if (empty($this->completionData)) {

            $specAssessmentComplete = [];
            $participantProgress  = [];

            foreach ($this->unitImplementations as $unitImpl) {

                $unitSpec   = $unitImpl->getSpecification();
                $unitSpecId = $unitSpec->getId();

                foreach ($unitImpl->getAssessables() as $implAssessment) {

                    $specAssessment   = $implAssessment->getSpecificationAssessment();
                    if($specAssessment === null) {
                        //skip assessments that have no spec
                        continue;
                    }
                    $specAssessmentId = $specAssessment->getId();

                    if (empty($specAssessmentComplete[$unitSpecId][$specAssessmentId])) {
                        // assume complete until told otherwise
                        $specAssessmentComplete[$unitSpecId][$specAssessmentId] = true;
                    }

                    foreach ($implAssessment->getAllParticipants() as $participant) {

                        $completed = $participant->getAllResponses()->filter(function (IResponse $response) { return $response->isSubmitted(); })->count() > 0;

                        $participantProgress[$unitSpecId][$participant->getUser()->getId()][$specAssessmentId] = $completed;

                        if (!$completed) {
                            // if ANY participant who maps onto a SPEC assessment has NO responses, that assessment is considered not completed overall.
                            $specAssessmentComplete[$unitSpecId][$specAssessmentId] = false;
                        }

                    }

                }

                $this->completionData[$unitSpecId] = [
                    'unitSpec'            => $unitSpec,
                    'assessmentCompletions' => $specAssessmentComplete[$unitSpecId],
                    'participantProgress' => $participantProgress[$unitSpecId]
                ];
            }

        }

        return $this->completionData;
    }


    /**
     * @param array $assessmentCompletions
     * @param array $participantProgress
     * @return array
     */
    private function reduceCompletion(array $assessmentCompletions, array $participantProgress)
    {
        if (empty($assessmentCompletions)) {
            $assessmentCompletions = [0];
        }

        if (empty($participantProgress)) {
            $participantProgress = [[0]];
        }

        return [
            'mostAdvancedStudent' => 100 * max(array_map(function (array $progress) { return array_sum($progress) / count($progress); }, $participantProgress)),
            'classCompletions'    => 100 * array_sum($assessmentCompletions) / count($assessmentCompletions),
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

            $specAssessmentComplete = $unitCompletionData['assessmentCompletions'];
            $participantProgress  = $unitCompletionData['participantProgress'];

            $progress[$unitSpecId] = $this->reduceCompletion($specAssessmentComplete, $participantProgress);
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
        $assessmentCompletions = [];
        $participantProgress = [];

        // flatten the unit specs out of the data, accommodating students who exist in more than one unit
        foreach ($this->getCompletionData() as $unitSpecId => $unitCompletionData) {

            // Did this UnitSpec have any AssessmentSpecs inside it?
            if (is_array($unitCompletionData['assessmentCompletions'])) {
                foreach ($unitCompletionData['assessmentCompletions'] as $specAssessmentId => $assessmentCompletion) {
                    if (!array_key_exists($specAssessmentId, $assessmentCompletions)) {
                        $assessmentCompletions[$specAssessmentId] = true;
                    }
                    $assessmentCompletions[$specAssessmentId] = min($assessmentCompletions[$specAssessmentId], $assessmentCompletion);
                }
            }

            // Did this UnitSpec have any AssessmentSpecs inside it?
            if (is_array($unitCompletionData['participantProgress'])) {
                foreach ($unitCompletionData['participantProgress'] as $participantId => $progress) {
                    foreach ($progress as $specAssessmentId => $isComplete) {
                        if (empty($participantProgress[$participantId][$specAssessmentId])) {
                            $participantProgress[$participantId][$specAssessmentId] = false;
                        }
                        $participantProgress[$participantId][$specAssessmentId] = max($participantProgress[$participantId][$specAssessmentId], $isComplete);
                    }
                }
            }
        }

        return $this->reduceCompletion($assessmentCompletions, $participantProgress);
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

            /** @var IAssessable[] $assessments */
            $assessments = [];

            /** @var User[] $participants */
            $users = [];

            foreach ($this->unitImplementations as $unitImplementation) {
                // participants can exist in units simultaneously, so we need to flatten according to specAssessment before summating.
                foreach ($unitImplementation->getAssessables() as $implAssessment) {

                    if(null === $implAssessment->getSpecificationAssessment()) {
                        //skip activities with no specification
                        continue;
                    }
                    $specAssessmentId = $implAssessment->getSpecificationAssessment()->getId();

                    if (empty($assessments[$specAssessmentId])) {
                        // only want one of these
                        $assessments[$specAssessmentId] = clone $implAssessment->getSpecificationAssessment();
                    }

                    $specAssessment = $assessments[$specAssessmentId];

                    foreach ($implAssessment->getAllParticipants() as $implParticipant) {

                        if (!$specAssessment->isParticipant($implParticipant->getUser())) {
                            // dupe the implParticipant object, linked to the specAssessment
                            $specAssessment->getAllParticipants()->add(new Participant(
                                $implParticipant->getUser(), $specAssessment
                            ));

                            // collate deduped list of users
                            $users[$implParticipant->getUser()->getId()] = $implParticipant->getUser();
                        }

                        $specParticipant = $specAssessment->getParticipant($implParticipant->getUser());

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
                                $specFeedback->setAssessable($specAssessment);
                                $specParticipant->setAllFeedbacks(new GenericCollection($specFeedback, [$specFeedback]));
                                $specAssessment->getFeedbacks()->add($specFeedback);
                            }

                        } else {
                            // drop that feedback straight in
                            $specFeedback = clone $implFeedback;
                            $specFeedback->setAssessable($specAssessment);
                            $specParticipant->setAllFeedbacks(new GenericCollection($specFeedback, [$specFeedback]));
                            $specAssessment->getFeedbacks()->add($specFeedback);
                        }
                    }

                }
            }

            $assessments = new Collection($assessments);

            foreach ($users as $userId => $user) {
                $this->gradeAverages[$userId] = [
                    'user'    => $user,
                    'average' => $summator->summateAllAssessablesForUser($assessments, $user, true)
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
     * Returns five-point grade data from unit IMPLEMENTATIONS for all participants, indexed by unit SPECIFICATION assessment
     * Applicable to the assessment box-and-whisker graph
     *
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @return IFeedback[][]
     */
    public function getCohortGradesByAssessment(GenericCollection $unitImplementations)
    {
        // shim around old code
        return (new AssessmentResults($unitImplementations))->getResults();
    }


    /**
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @param Term $term
     * @return Collection|Collection[]|IAssessable[][]
     */
    public function getAssessments(GenericCollection $unitImplementations, Term $term)
    {
        // shim around old code
        return (new AssessmentList($unitImplementations, $term))->getAssessments();
    }


    /**
     * Returns five-point grade data from unit implementations for all participants, indexed by rubric indicator
     * Applicable to the rubric box-and-whisker graph
     *
     * (Cuts the same data as getCohortGradesByAssessment() but by indicators instead of assessment.)
     *
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @param Collection|User[][] $unitImplStudents Grouped by unitImpl.id,
     * @return array
     */
    public function getCohortGradesByIndicator(GenericCollection $unitImplementations, Collection $unitImplStudents)
    {
        // exploit old class
        return (new AllRubrics($unitImplementations, $unitImplStudents))->getRubricData();
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

        /** @var Collection|Collection[]|FolderLog[][] $accessLogs */
        $accessLogs = FolderLogMapper::getInstance()->findByColumnList([
            'user' => array_keys($cohort),
            'cid'  => $folderIds
        ])->groupByCallback(function (FolderLog $log) {
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
