<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
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
     * A cached list of users. Indexed by folder.id, user.id
     *
     * @var Collection|GenericCollection[]|User[][]
     */
    protected $cacheForFolder;


    /**
     * Constructor
     *
     * @param External_ClassInterface $externalDb
     * @param UserAccessMapper        $userAccessMapper
     * @param UserMapper              $userMapper
     * @param ResponseDataSource      $responseDataSource
     * @param FeedbackDataSource      $feedbackDataSource
     */
    public function __construct(
        External_ClassInterface $externalDb,
        UserAccessMapper $userAccessMapper,
        UserMapper $userMapper,
        ResponseDataSource $responseDataSource,
        FeedbackDataSource $feedbackDataSource
    ) {
        $this->externalDb         = $externalDb;
        $this->userAccessMapper   = $userAccessMapper;
        $this->userMapper         = $userMapper;
        $this->responseDataSource = $responseDataSource;
        $this->feedbackDataSource = $feedbackDataSource;
        $this->cacheForFolder     = new Collection();
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
     * @param null|User   $user
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    public function loadParticipationIntoAssessment(IAssessable $assessment, User $user = null): void
    {
        $this->loadParticipationIntoAssessmentCollection(
            new GenericCollection(IAssessable::class, [$assessment]),
            $user
        );
    }


    /**
     * Loads participation into all assessments.
     *
     * This includes records for responses and feedbacks for every participant in each assessment.
     *
     * @param Collection|IAssessable[] $assessments
     * @param User                     $user
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    public function loadParticipationIntoAssessmentCollection(Collection $assessments, User $user = null): void
    {
        /** @var Collection|IAssessable[] $assessableWithDescendants */
        $assessableWithDescendants = new GenericCollection(IAssessable::class);
        $this->mergeSubAssessments($assessableWithDescendants, $assessments);

        // Load participants for all assessments, plus activities in projects
        $this->findForAssessmentCollection($assessableWithDescendants, $user);
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
     * @param IAssessable[]|GenericCollection $assessments Indexed by assessment.id
     * @param null|User                       $user
     * @throws UnexpectedValueException
     * @throws DomainException If user has interaction but not participant record
     */
    protected function findForAssessmentCollection(GenericCollection $assessments, User $user = null): void
    {
        $dynamicPlans = $this->loadPlansAndParticipants($assessments);

        $this->loadDynamicPlanParticipants($dynamicPlans);

        // Add Responses
        $this->loadResponses(
            $assessments,
            $this->responseDataSource->findForAssessmentCollection($assessments, $user)
        );

        // Add Feedback
        $this->loadFeedbacks(
            $assessments,
            $this->feedbackDataSource->findForAssessmentCollection($assessments, $user)
        );

        // Sort by name after all that
        // TODO this won't work now that we have IAssessable->Plan->Participants. Should we cache on IAssessable?
        // TODO probably should make this a view-layer responsibility.
        // TODO You could do $twigContext[participants] = $assessable->getParticipants()->sort(SortableFunctionObject)
//        foreach ($assessments as $assessment) {
//            $participants = $assessment->getAllParticipants()->sortByCallback([$this, 'sortCallback']);
//            $assessment->setAllParticipants($participants);
//        }
    }


    /**
     * @param PlanObject[]|GenericCollection $dynamicPlans
     * @throws UnexpectedValueException
     */
    private function loadDynamicPlanParticipants(GenericCollection $dynamicPlans): void
    {
        // TODO SA-5354 - fetch excluded users, grouped by assessment
        /** @var User[][]|Collection $excludedUsers Indexed by assessment.id, user.id */
        $excludedUsers = new Collection();


        // Load dynamic participants into dynamic plans
        foreach ($dynamicPlans as $dynamicPlan) {
            $assessment           = $dynamicPlan->getAssessable();
            $explicitParticipants = $assessment->getAllParticipants();
            $excludedUsers        = $excludedUsers[$assessment->getId()];

            // It's acceptable to findAllStudents in a loop for now, because the list is cached
            foreach ($this->findAllStudentsForAssessment($assessment) as $user) {
                // Is this user already an explicit participant?
                if($explicitParticipants->keyExists($user->getId())){
                    continue;
                }
                // Is this user excluded?
                if($excludedUsers && $excludedUsers->keyExists($user->getId())){
                    continue;
                }
                $dynamicPlan->getParticipants()[$user->getId()] = new ParticipantObject($user, $dynamicPlan);
            }
        }
    }


    /**
     * @param IAssessable[]|GenericCollection $assessables
     * @param IResponse[][]|Collection        $allResponses
     * @throws DomainException If a response is encountered for a user who is not an assessment participant
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
                $participantUser = $assessable->getParticipantIfExtant($responseUser);
                if (!$participantUser) {
                    throw new DomainException(sprintf(
                            'User (id: %s) has a response (id: %s) for assessment (id: %s) but no participant record',
                            $responseUser->getId(),
                            $response->getId(),
                            $assessable->getId()
                        )
                    );
                }
                $participantUser->getAllResponses()[$response->getId()] = $response;
            }
        }
    }


    /**
     * @param IAssessable[]|GenericCollection $assessments
     * @param IFeedback[][]|Collection        $allFeedback
     *
     * @throws UnexpectedValueException
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
                $feedbackUser = $this->userMapper->findByIdWithFallback($feedback->getReviewee()->getId());
                $participantUser = $assessment->getParticipantIfExtant($feedbackUser);

                if (!$participantUser) {
                    throw new DomainException(sprintf(
                            'User (id: %s) has a feedback (id: %s) for assessment (id: %s) but no participant record',
                            $feedbackUser->getId(),
                            $feedback->getId(),
                            $assessment->getId()
                        )
                    );
                }
                $participantUser->getAllFeedbacks()->add($feedback);

                // Feedbacks may have a stub response.
                // If so, use it to find the actual response, and associate them together.
                // (Assume that the stub response lives in the same assessment!)
                $stubResponse = $feedback->getResponse();
                if ($stubResponse instanceof IResponse && $stubResponse->getId()) {
                    $stubResponseId = $stubResponse->getId();

                    foreach ($participantUser->getAllResponses() as $response) {
                        // For now assume that the types of response and feedback match
                        if ($stubResponseId == $response->getId()) {
                            $response->getAllFeedback()->add($feedback);
                            $feedback->setResponse($response);
                            break;
                        }
                    }
                }
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
    public function sortCallback(ParticipantObject $a, ParticipantObject $b)
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
     * @return Collection|User[]
     * @throws \UnexpectedValueException If unable to merge student collections
     */
    protected function findAllStudentsForAssessment(IAssessable $assessment)
    {
        $students = new Collection();

        $context = $assessment->getContext();
        if ($context instanceof UnitSpecification\Model) {
            // Unit specifications have no participants
            return $students;
        }

        $folder = $context->getFolder();

        // Use cached list of users for a folder
        if (!isset($this->cacheForFolder[$folder->getId()])) {
            // Cache isn't set - populate

            // Class members: convert to list of users
            $classCodes = $this->externalDb->getStudents($folder->getCodeString());
            if (!empty($classCodes)) {
                $students = $students->merge($this->userMapper->findByColumn('synergy_id', array_column($classCodes, 'synergy_id')));
            }

            // I want to search for ALL read-level users, not just students
            // Deliberately don't search external DB, I have already done that
            $students                               = $students->mergeUnique(
                $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, true),
                'id'
            );
            $this->cacheForFolder[$folder->getId()] = $students;
        }
        return $this->cacheForFolder[$folder->getId()];
    }

    /**
     * Recursively walks the assessment tree and merges them into a flat collection
     *
     * @param IAssessable[]|GenericCollection $merged Indexed by assessment.id
     * @param IAssessable[]|GenericCollection $tree
     */
    private function mergeSubAssessments(GenericCollection $merged, GenericCollection $tree): void
    {
        foreach($tree as $assessable){
            $merged[$assessable->getId()] = $assessable;
            if ($assessable instanceof Project) {
                $this->mergeSubAssessments($merged, $assessable->getAssessables());
            }
        }
    }


    /**
     * Loads all Plans and Participants into the Assessables, and returns the set of dynamic plans
     *
     * @param IAssessable[]|GenericCollection $assessables Indexed by assessment.id
     * @return PlanObject[]|GenericCollection Indexed by plan.id
     */
    private function loadPlansAndParticipants(GenericCollection $assessables): GenericCollection
    {
        $dynamicPlans = new GenericCollection(PlanObject::class);

        // Find all participant and plan models for the assessments
        $participantMapper = Participant\Mapper::getInstance();
        $participantModels = $participantMapper->findByColumn('pp.assessment_id', $assessables->keys());

        foreach($participantModels as $participantModel){
            $planModel  = $participantModel->getPlan();
            $assessable = $assessables[$planModel->getAssessment()->getId()];

            // Does this assessable not contain this plan?
            $planObject = $assessable->getPlans()[$planModel->getId()];
            if(!$planObject){
                // Create a Plan Domain Object
                $planObject = new PlanObject($assessable);
                $assessable->getPlans()[$planModel->getId()] = $planObject;

                // Copy all attributes from data model to domain object
                $planObject
                    ->setId($planModel->getId())
                    ->setIsDynamic($planModel->getIsDynamic())
                    ->setDescription($planModel->getDescription())
                    ->setPublishDate($planModel->getPublishDate())
                    ->setOpenDate($planModel->getOpenDate())
                    ->setDueDate($planModel->getDueDate())
                    ->setCloseDate($planModel->getCloseDate())
                    ->setCreatedAt($planModel->getCreatedAt())
                    ->setUpdatedAt($planModel->getUpdatedAt())
                    ->setDeletedAt($planModel->getDeletedAt());

                // And store it if dynamic so we can later insert class/group members dynamically
                if($planObject->getIsDynamic()){
                    $dynamicPlans[$planObject->getId()] = $planObject;
                }
            }

            // Create a Participant Domain object
            $participantObject = new ParticipantObject($participantModel->getUser(), $planObject);
            $planObject->getParticipants()[$participantModel->getUser()->getId()] = $participantObject;

            // Copy all attributes from data model to domain object
            $participantObject
                ->setId($participantModel->getId())
                ->setCreatedAt($participantModel->getCreatedAt())
                ->setUpdatedAt($participantModel->getUpdatedAt())
                ->setExcludedAt($participantModel->getExcludedAt());
        }
        return $dynamicPlans;
    }
}
