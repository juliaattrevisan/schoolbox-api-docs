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
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\DefinitionException;
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
     * @param bool        $includeExcluded Should we include explicitly excluded participants in graph?
     * @param null|User   $user
     * @return IAssessable $assessment, with participation loaded.
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     */
    public function loadParticipationIntoAssessment(IAssessable $assessment, bool $includeExcluded, User $user = null): IAssessable
    {
        $collection = $this->loadParticipationIntoAssessmentCollection(
            new GenericCollection(IAssessable::class, [$assessment]),
            $includeExcluded,
            $user
        );
        return $collection->first();
    }


    /**
     * Loads participation into all assessments.
     *
     * This includes records for responses and feedbacks for every participant in each assessment.
     *
     * @param Collection|IAssessable[] $assessments
     * @param bool                     $includeExcluded Should we include explicitly excluded participants in graph?
     * @param User|null                $user
     * @return Collection|IAssessable[] $assessments, with participation loaded.
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to derive project participant collection
     */
    public function loadParticipationIntoAssessmentCollection(Collection $assessments, bool $includeExcluded, User $user = null): Collection
    {
        /** @var Collection|IAssessable[] $assessableWithDescendants */
        $assessableWithDescendants = new GenericCollection(IAssessable::class);
        $this->mergeSubAssessments($assessableWithDescendants, $assessments);

        // Load participants for all assessments, plus activities in projects
        $this->findForAssessmentCollection($assessableWithDescendants, $includeExcluded, $user);

        // Insert project participant objects into any project
        foreach ($assessableWithDescendants as $assessable) {
            if ($assessable instanceof Project) {
                $this->aggregator->aggregate($assessable);
            }
        }
        return $assessableWithDescendants;
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
     * @param bool                     $includeExcluded Should we include explicitly excluded participants in graph?
     * @param null|User                       $user
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     */
    protected function findForAssessmentCollection(GenericCollection $assessments, bool $includeExcluded, User $user = null): void
    {
        $this->loadPlansAndParticipants($assessments);

        $dynamicPlans = new GenericCollection(PlanObject::class);
        foreach ($assessments as $assessment) {
            $dynamicPlan = $assessment->getDynamicPlan();
            if ($dynamicPlan) {
                $dynamicPlans->add($dynamicPlan);
            }
        }

        // We do need excluded participants to be present to determine whether to whether to
        // insert students as dynamic participants.
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

        if(!$includeExcluded){
            // But we may wish to then remove these participants so they are not present in the domain graph
            $this->removeExcludedParticipants($assessments);
        }
    }


    /**
     * Strips any excluded participants from assessment plans so that they are not present in domain graph.
     *
     * @param GenericCollection|IAssessable[] $assessables
     */
    private function removeExcludedParticipants(GenericCollection $assessables): void
    {
        foreach($assessables as $assessable) {
            foreach ($assessable->getPlans() as $plan) {
                foreach ($plan->getParticipants() as $participant) {
                    if ($participant->isExcluded()) {
                        $plan->getParticipants()->offsetUnset($participant->getUser()->getId());
                    }
                }
            }
        }
    }


    /**
     * @param PlanObject[]|GenericCollection $dynamicPlans
     * @throws UnexpectedValueException If unable to merge student collections
     */
    private function loadDynamicPlanParticipants(GenericCollection $dynamicPlans): void
    {
        // Load dynamic participants into dynamic plans
        foreach ($dynamicPlans as $dynamicPlan) {
            $assessment           = $dynamicPlan->getAssessable();
            $explicitParticipants = $assessment->getAllParticipants();

            // It's acceptable to findAllStudents in a loop for now, because the list is cached
            foreach ($this->findAllStudentsForAssessment($assessment) as $user) {
                // Is this user already an explicit participant? (Either an inclusion or exclusion)
                if ($explicitParticipants->keyExists($user->getId())) {
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
                $responseUser    = $this->userMapper->findByIdWithFallback($response->getSubmittedOnBehalfOf()->getId());
                $participantUser = $assessable->getParticipantIfExtant($responseUser);
                if (!$participantUser) {
                    $msg = sprintf(
                        'User (id: %s) has a response (id: %s) for assessment (id: %s) but no participant record',
                        $responseUser->getId(),
                        $response->getId(),
                        $assessable->getId()
                    );
                    throw new DomainException($msg);
                }
                $participantUser->getAllResponses()[$response->getId()] = $response;
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
                $participantUser = $assessment->getParticipantIfExtant($feedbackUser);

                if (!$participantUser) {
                    $msg = sprintf(
                        'User (id: %s) has a feedback (id: %s) for assessment (id: %s) but no participant record',
                        $feedbackUser->getId(),
                        $feedback->getId(),
                        $assessment->getId()
                    );
                    throw new DomainException($msg);
                }
                $participantUser->getAllFeedbacks()->add($feedback);

                // Feedbacks may have a stub response.
                // If so, use it to find the actual response, and associate them together.
                // (Assume that the stub response lives in the same assessment!)
                $stubResponse = $feedback->getResponse();
                if ($stubResponse && $stubResponse->getId()) {
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
     * @return Collection|User[]
     * @throws UnexpectedValueException If unable to merge student collections
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
                // Create a Participant Domain object
                $user                                          = $participantModel->getUser();
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
     * @return Participant\Mapper|\Schoolbox\Mapper
     * @throws DefinitionException
     */
    public function getParticipantMapper()
    {
        return $this->getPlanMapper()->getJoinMapper('participants');
    }
}
