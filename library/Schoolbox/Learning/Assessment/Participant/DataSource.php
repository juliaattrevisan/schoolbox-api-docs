<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant;

use External_ClassInterface;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant as ParticipantObject;
use Schoolbox\Learning\Assessment\Plan as PlanObject;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use DomainException;

/**
 * The single source of truth for assessment participants.
 */
class DataSource
{

    /**
     * Data source for class lists
     *
     * @var External_ClassInterface
     */
    protected $externalDb;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * Data source for users themselves
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Data source for assessment responses
     *
     * @var ResponseDataSource
     */
    protected $responseDataSource;

    /**
     * Data source for assessment feedback
     *
     * @var FeedbackDataSource
     */
    protected $feedbackDataSource;

    /**
     * @var Participant\Plan\Mapper
     */
    protected $planMapper;

    /**
     * A cached list of users. Indexed by folder.id, user.id
     *
     * @var Collection|GenericCollection[]|User[][]
     */
    protected $cacheForFolder;

    /** @var Aggregator */
    private $aggregator;


    /**
     * Constructor
     *
     * @param External_ClassInterface $externalDb
     * @param UserAccessMapper        $userAccessMapper
     * @param UserMapper              $userMapper
     * @param ResponseDataSource      $responseDataSource
     * @param FeedbackDataSource      $feedbackDataSource
     * @param Participant\Plan\Mapper $planMapper
     */
    public function __construct(
        External_ClassInterface $externalDb,
        UserAccessMapper $userAccessMapper,
        UserMapper $userMapper,
        ResponseDataSource $responseDataSource,
        FeedbackDataSource $feedbackDataSource,
        Participant\Plan\Mapper $planMapper
    ) {
        $this->externalDb         = $externalDb;
        $this->userAccessMapper   = $userAccessMapper;
        $this->userMapper         = $userMapper;
        $this->responseDataSource = $responseDataSource;
        $this->feedbackDataSource = $feedbackDataSource;
        $this->planMapper         = $planMapper;
        $this->cacheForFolder     = new Collection();
        $this->aggregator         = new Aggregator();
    }


    /**
     * Load participants into an IAssessable.
     *
     * This includes records for responses and feedbacks for every participant in $assessment
     * If the $assessment is a Project, also loads participants into project sub-assessments.
     *
     * Note: If you are calling this from a loop, you should use loadParticipationIntoAssessment()
     *       as it is more efficient when loading responses and feedbacks.
     *
     * @param IAssessable $assessment
     * @param null|User   $user    If specified, only load this user's interactions
     * @param array       $options Options for findByColumnList
     * @return IAssessable $assessment, with participation loaded.
     * @throws UnexpectedValueException If unable to derive project participant collection
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     */
    public function loadParticipationIntoAssessment(IAssessable $assessment, ?User $user = null, array $options = []): IAssessable
    {
        $this->loadParticipationIntoAssessmentCollection(
            new GenericCollection(IAssessable::class, [$assessment]),
            $user,
            false,
            false,
            $options
        );
        return $assessment;
    }


    /**
     * Loads participation into all assessments.
     *
     * This includes records for responses and feedbacks for every participant in each assessment.
     *
     * @param GenericCollection|IAssessable[] $assessments
     * @param User|null                $user                If specified, only load this user's interactions
     * @param bool                     $removeExcluded      Set to true to exclude excluded non-member participants
     * @param bool                     $assumeParticipation if true, assume $user is a participant in the assessment
     *                                                      without checking the assessment's folder.
     * @param array                    $options Options for findByColumnList
     * @return GenericCollection|IAssessable[] $assessments, with participation loaded.
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to derive project participant collection
     */
    public function loadParticipationIntoAssessmentCollection(
        GenericCollection $assessments,
        ?User $user = null,
        bool $removeExcluded = false,
        bool $assumeParticipation = false,
        array $options = []
    ): GenericCollection {
        /** @var GenericCollection|IAssessable[] $assessableWithDescendants */
        $assessableWithDescendants = new GenericCollection(IAssessable::class);
        $this->mergeSubAssessments($assessableWithDescendants, $assessments);

        // Load participants for all assessments, plus activities in projects
        $this->findForAssessmentCollection($assessableWithDescendants, $user, $removeExcluded, $assumeParticipation, $options);

        // Insert project participant objects into any project
        foreach ($assessableWithDescendants as $assessable) {
            if ($assessable instanceof Project) {
                $this->aggregator->aggregate($assessable);
            }
        }
        return $assessments;
    }


    /**
     * Find all Participants for a given assessment.
     *
     * This is a union of:
     * * any students in the context's folder's class
     * * any read-level members of the context's folder's group
     * * anyone who has submitted a Response for this assessment
     * * anyone who has been given Feedback for this assessment
     *
     * This method sets the participants into each $assessment
     *
     * @param IAssessable[]|GenericCollection $assessments         Indexed by assessment.id
     * @param null|User                       $user                If specified, only load this user's interactions
     * @param bool                            $removeExcluded      if true, exclude excluded non-member participants
     * @param bool                            $assumeParticipation if true, assume $user is a participant in the
     *                                                             assessment without checking the assessment's folder.
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function findForAssessmentCollection(
        GenericCollection $assessments,
        User $user = null,
        bool $removeExcluded = false,
        bool $assumeParticipation = false,
        array $options = []
    ): void {
        // Clear all participation from $assessments
        $this->clearParticipants($assessments);

        $this->loadPlansAndParticipants($assessments);

        $dynamicPlans = new GenericCollection(PlanObject::class);
        foreach ($assessments as $assessment) {
            // Projects do not have persisted plans and participants.
            if ($assessment instanceof Project) {
                continue;
            }
            $dynamicPlan = $assessment->getDynamicPlan();
            if ($dynamicPlan) {
                $dynamicPlans->add($dynamicPlan);
            }
        }

        // We do need excluded participants to be present to determine whether to whether to
        // insert students as dynamic participants.
        $this->loadDynamicPlanParticipants($dynamicPlans, $user, $assumeParticipation);

        // Add Responses
        $this->loadResponses(
            $assessments,
            $this->responseDataSource->findForAssessmentCollection($assessments, $user, $options)
        );

        // Add Feedback
        $this->loadFeedbacks(
            $assessments,
            $this->feedbackDataSource->findForAssessmentCollection($assessments, $user, $options)
        );

        if ($removeExcluded) {
            $this->removeExcluded($assessments);
        }
    }


    /**
     * Adds participation objects for students who have a class/group relationship
     * to the assessment's folder and do not have an explicit participation record.
     *
     * @param PlanObject[]|GenericCollection $dynamicPlans
     * @param User|null                      $user                If provided, load only this user's participation
     * @param bool                           $assumeParticipation if true, assume $user is a participant in the
     *                                                            assessment without checking the assessment's folder.
     *                                                            Some places which may have called this may have
     *                                                            already loaded a student's folders, so they already
     *                                                            know $user is a participant.
     * @throws UnexpectedValueException If unable to merge student collections
     */
    private function loadDynamicPlanParticipants(
        GenericCollection $dynamicPlans,
        User $user = null,
        bool $assumeParticipation = false
    ): void {
        // Load dynamic participants into dynamic plans
        foreach ($dynamicPlans as $dynamicPlan) {
            $assessment = $dynamicPlan->getAssessable();
            $explicitParticipants = $assessment->getAllParticipants();

            if ($user && $assumeParticipation) {
                // For efficiency reasons:
                // bypass findAllStudentsForAssessment and assume that $user is
                // a participant of this plan
                if (!$explicitParticipants->keyExists($user->getId())) {
                    $dynamicPlan->getParticipants()[$user->getId()] = new ParticipantObject($user, $dynamicPlan);
                }
            } else {
                // It's acceptable to findAllStudents in a loop for now, because the list is cached
                foreach ($this->findAllStudentsForAssessment($assessment) as $student) {
                    // Is this user already an explicit participant? (Either an inclusion or exclusion)
                    if ($explicitParticipants->keyExists($student->getId())) {
                        continue;
                    }

                    // If a user was provided: only load their participation
                    if (!$user || $user->getId() === $student->getId()) {
                        $dynamicPlan->getParticipants()[$student->getId()] = new ParticipantObject($student, $dynamicPlan);
                    }
                }
            }
        }
    }


    /**
     * @param GenericCollection|IAssessable[] $assessments
     */
    private function clearParticipants(GenericCollection $assessments): void
    {
        foreach ($assessments as $assessment) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $assessment->unsetUserParticipation($participant->getUser());
            }
        }
    }


    /**
     * @param IAssessable[]|GenericCollection $assessables
     * @param Collection        $allResponses
     * @throws \Schoolbox\Mapper\Exception
     */
    private function loadResponses(GenericCollection $assessables, Collection $allResponses): void
    {
        // Load into each assessment
        foreach ($assessables as $assessable) {
            $assessmentResponses = $allResponses[$assessable->getId()];

            if (!$assessmentResponses) {
                continue;
            }

            foreach ($assessmentResponses as $response) {
                //Ensure response user is fully hydrated
                $responseUser = $this->userMapper->findByIdWithFallback($response->getSubmittedOnBehalfOf()->getId());

                // participants for assessment are no longer hydrated for deleted user.
                if ($responseUser->isDeleted()) {
                    continue;
                }

                $participantUser = $assessable->getParticipantIfExtant($responseUser);

                // Don't load interactions for excluded participants.
                if ($participantUser && !$participantUser->isExcluded()) {
                    $participantUser->getAllResponses()[$response->getId()] = $response;
                }

                /* else: participant not found
                 * noop
                 *
                 * Why this should result in a critical failure of this page, I don't understand
                 *
                 * Their participations should be attributed to an unknown user, at worst;
                 * so that we remember the data that was put here, even if nobody can own it at the moment
                 * We don't know the reasons why a participant may no longer exist here
                 * but allow the system to continue as best it can instead of just dying entirely
                 *
                 * Otherwise you could disregard their involvement
                 */
            }
        }
    }


    /**
     * @param IAssessable[]|GenericCollection $assessments
     * @param IFeedback[][]|Collection        $allFeedback
     * @throws DomainException If a feedback is encountered for a user who is not an assessment participant
     */
    private function loadFeedbacks(GenericCollection $assessments, Collection $allFeedback): void
    {
        foreach ($assessments as $assessment) {
            $assessmentFeedback = $allFeedback[$assessment->getId()];
            if (!$assessmentFeedback) {
                continue;
            }
            foreach ($assessmentFeedback as $feedback) {
                //Ensure response user is fully hydrated
                $feedbackUser    = $this->userMapper->findByIdWithFallback($feedback->getReviewee()->getId());
                // participants for assessment are no longer hydrated for deleted user.
                if ($feedbackUser->isDeleted()) {
                    continue;
                }

                $participant = $assessment->getParticipantIfExtant($feedbackUser);
                if ($participant && !$participant->isExcluded()) {
                    $participant->getAllFeedbacks()->add($feedback);

                    // Feedbacks may have a stub response.
                    // If so, use it to find the actual response, and associate them together.
                    // (Assume that the stub response lives in the same assessment!)
                    $stubResponse = $feedback->getResponse();
                    if ($stubResponse && $stubResponse->getId()) {
                        $stubResponseId = $stubResponse->getId();

                        foreach ($participant->getAllResponses() as $response) {
                            // For now assume that the types of response and feedback match
                            if ($stubResponseId == $response->getId()) {
                                $response->getAllFeedback()->add($feedback);
                                $feedback->setResponse($response);
                                break;
                            }
                        }
                    }
                }

                // Otherwise: `$feedbackUser` is either excluded or not a
                // participant at all
                // As above in `loadResponses`: this isn't a fatal error, just
                // ignore that the user isn't a participant
            }
        }
    }


    /**
     * Sort callback: sort by surname, then first name, then ID.
     *
     * @param ParticipantObject $a
     * @param ParticipantObject $b
     * @return int
     */
    public function sortCallback(ParticipantObject $a, ParticipantObject $b): int
    {
        $lastCmp = strcasecmp($a->getUser()->getLastname(), $b->getUser()->getLastname());
        if ($lastCmp != 0) {
            return $lastCmp;
        }
        $firstCmp = strcasecmp($a->getUser()->getFirstname(), $b->getUser()->getFirstname());
        if ($firstCmp != 0) {
            return $firstCmp;
        }
        // ID comparison:
        // if a.user.id < b.user.id, then a < b
        // if a.user.id > b.user.id, then a > b
        return ($a->getUser()->getId() - $b->getUser()->getId());
    }


    /**
     * Finds all students who are participating in this assessment by virtue of class/group membership
     *
     * @param IAssessable $assessment
     * @return Collection|User[] indexed by userId
     * @throws UnexpectedValueException If unable to merge student collections
     */
    protected function findAllStudentsForAssessment(IAssessable $assessment)
    {
        $students = new Collection();

        $context = $assessment->getBaseContext();
        if ($assessment->isSpecification()) {
            // Assessment specifications have no participants
            return $students;
        }
        $folder = $context->getFolder();

        // Use cached list of users for a folder
        if (!isset($this->cacheForFolder[$folder->getId()])) {
            // Cache isn't set - populate
            if ($folder->getCodes() && count($folder->getCodes()) > 0) {
                // Class members: convert to list of users
                $classCodes = $this->externalDb->getStudents($folder->getCodeString());
                if (!empty($classCodes)) {
                    $pb = $this->userMapper->getPredicateBuilder()
                        ->in('synergy_id', array_column($classCodes, 'synergy_id'))
                        ->equals('g.group_type', Role::STUDENT);

                    $students = $students->merge($this->userMapper->findByPredicates($pb))->indexBy('id');
                }
            }

            // I want to search for ALL read-level users, not just students
            // Deliberately don't search external DB, I have already done that
            $students = $students->mergeUnique(
                $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, true),
                'id'
            )->indexBy('id');

            $this->cacheForFolder[$folder->getId()] = $students;
        }
        return $this->cacheForFolder[$folder->getId()];
    }


    /**
     * Removes participation objects for students who are excluded and
     * don't have a class/group relationship to the assessment's folder.
     *
     * @param IAssessable[]|GenericCollection $assessments
     * @throws UnexpectedValueException If unable to merge student collections
     */
    private function removeExcluded(GenericCollection $assessments): void
    {
        foreach ($assessments as $assessment) {
            $currentMembers = $this->findAllStudentsForAssessment($assessment);
            foreach ($assessment->getPlans() as $plan) {
                foreach ($plan->getParticipants() as $userId => $participant) {
                    if ($participant->isExcluded() && !$currentMembers->offsetExists($userId)) {
                        $plan->getParticipants()->offsetUnset($userId);
                    }
                }
            }
        }
    }


    /**
     * Recursively walks the assessment tree and merges them into a flat collection
     *
     * @param IAssessable[]|GenericCollection $merged Indexed by assessment.id
     * @param IAssessable[]|GenericCollection $tree
     */
    private function mergeSubAssessments(GenericCollection $merged, GenericCollection $tree): void
    {
        foreach ($tree as $assessable) {
            $merged[$assessable->getId()] = $assessable;
            if ($assessable instanceof Project) {
                $this->mergeSubAssessments($merged, $assessable->getAssessables());
            }
        }
    }


    /**
     * Loads all Plans and Participants (including exclusions) into the Assessables
     *
     * @param IAssessable[]|GenericCollection $assessables Indexed by assessment.id
     * @throws DefinitionException If unable to locate a specified mapper join
     */
    private function loadPlansAndParticipants(GenericCollection $assessables): void
    {
        // Find all participant and plan models for the assessments
        $planMapper = $this->planMapper;
        $planModels = $planMapper->findPlansAndParticipantsForAssessments($assessables->keys());

        foreach ($planModels as $planModel) {
            $assessable = $assessables[$planModel->getAssessment()->getId()];

            // Create a Plan Domain Object
            $planObject                                  = new PlanObject($assessable);
            $assessable->getPlans()[$planModel->getId()] = $planObject;

            // Copy all attributes from data model to domain object
            $planObject
                ->setId($planModel->getId())
                ->setIsDynamic($planModel->getIsDynamic())
                ->setName($planModel->getName())
                ->setDescription($planModel->getDescription())
                ->setPublishDate($planModel->getPublishDate())
                ->setOpenDate($planModel->getOpenDate())
                ->setDueDate($planModel->getDueDate())
                ->setCloseDate($planModel->getCloseDate())
                ->setCreatedAt($planModel->getCreatedAt())
                ->setUpdatedAt($planModel->getUpdatedAt())
                ->setDeletedAt($planModel->getDeletedAt());


            foreach ($planModel->getParticipants() as $participantModel) {
                // Is this student already participating?
                // Don't load them if they are already, otherwise remove
                // from existing participation.
                $user                = $participantModel->getUser();
                $existingParticipant = $assessable->getParticipantIfExtant($user);
                if ($existingParticipant) {
                    if ($existingParticipant->getUpdatedAt()->gt($participantModel->getUpdatedAt())) {
                        continue;
                    }
                    $assessable->unsetUserParticipation($user);
                }

                // Create a Participant Domain object
                $participantObject                             = new ParticipantObject($user, $planObject);
                $planObject->getParticipants()[$user->getId()] = $participantObject;

                // Copy all attributes from data model to domain object
                $participantObject
                    ->setId($participantModel->getId())
                    ->setCreatedAt($participantModel->getCreatedAt())
                    ->setUpdatedAt($participantModel->getUpdatedAt())
                    ->setExcludedAt($participantModel->getExcludedAt());
            }
        }
    }


    /**
     * @return Participant\Plan\Mapper
     */
    public function getPlanMapper()
    {
        return $this->planMapper;
    }


    /**
     * @return Participant\Mapper
     * @throws DefinitionException
     */
    public function getParticipantMapper()
    {
        /** @var \Schoolbox\Assessment\Participant\Mapper $participantMapper */
        $participantMapper = $this->getPlanMapper()->getJoinMapper('participants');

        return $participantMapper;
    }
}
