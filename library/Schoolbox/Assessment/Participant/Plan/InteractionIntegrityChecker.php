<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\User;
use PDOException;
use Schoolbox\Mapper as SchoolboxMapper;
use UnexpectedValueException;
use InvalidArgumentException;

/**
 * Responsible for checking whether a participant interaction is the first for a [assessment:user] combination.
 * If so, inserts a participant record into the assessment's dynamic plan.
 */
class InteractionIntegrityChecker
{

    /**
     * @var Mapper
     */
    private $planMapper;

    /**
     * @var Participant\Mapper
     */
    private $participantMapper;


    /**
     * @param Mapper             $planMapper
     * @param Participant\Mapper $participantMapper
     */
    public function __construct(Mapper $planMapper, Participant\Mapper $participantMapper)
    {
        $this->planMapper        = $planMapper;
        $this->participantMapper = $participantMapper;
    }


    /**
     * Checks a student can receive interactions. This can occur only if:
     * - student is not excluded, and
     * - student has a participation record, or a dynamic plan exists for a participation record to be inserted into.
     *
     * This should be used as a pre-save check on the four participation interaction tables
     *
     * It does not necessarily belong to the dynamic plan, because it may not exist.
     * eg, assessment has explicit plan with 3 students, student#1 interacts, but is then excluded.
     *
     * @param User             $student
     * @param Assessment\Model $assessment
     * @return bool
     * @throws SchoolboxMapper\DefinitionException
     * @throws InvalidState If inserting an interaction would cause the system to enter an invalid state
     * @throws InvalidArgumentException If assessment not sufficiently hydrated
     * @throws SchoolboxMapper\Exception
     */
    public function assertCanReceiveInteractions(User $student, Assessment\Model $assessment): bool
    {
        $this->loadPlansAndParticipants($assessment);

        $participant = $this->findParticipation($student, $assessment);

        if ($participant) {
            if ($participant->isExcluded()) {
                // Did we find an exclusion record? You must not create interactions.
                throw new InvalidState("User ({$student->getId()}) has participation exclusion ({$participant->getId()})");
            }
        } else {
            if (!$assessment->getDynamicPlan()) {
                // No participation, but no dynamic plan?
                throw new InvalidState("User ({$student->getId()}) has no participation, but assessment ({$assessment->getId()}) has no dynamic plan");
            }
        }
        return true;
    }


    /**
     * Creates or updates a plan when resubmission is requested, to allow submission after the original close date
     * @param User             $student
     * @param Assessment\Model $assessment
     * @param string           $expireDateOffset
     * @throws SchoolboxMapper\Exception
     */
    public function createPlanForResubmission(User $student, Assessment\Model $assessment, string $expireDateOffset): void
    {
        if (!strtotime(trim($expireDateOffset))) {
            return;
        }

        $this->loadPlansAndParticipants($assessment);
        $participant = $this->findParticipation($student, $assessment);
        if (!$participant) {
            return;
        }

        $currentPlan = $participant->getPlan();
        $possibleCloseDate = \Alaress_Date_Format::now()->modify($expireDateOffset);
        $currentCloseDate  = $currentPlan->getIsDynamic() ? $assessment->getCloseDate() : $currentPlan->getCloseDate();
        if ($possibleCloseDate->lte($currentCloseDate)) {
            return;
        }

        if ($currentPlan->isDynamic()) {
            // Default plan - Create a new plan
            $newPlan = $this->planMapper->createModel()
                                        ->setIsDynamic(false)
                                        ->setAssessment($assessment)
                                        ->setName('Resubmit')
                                        ->setPublishDate($assessment->getPublishDate())
                                        ->setOpenDate($assessment->getOpenDate())
                                        ->setDueDate($assessment->getDueDate())
                                        ->setCloseDate($possibleCloseDate);
            $this->planMapper->save($newPlan);

            $participant->setPlan($newPlan);
            $this->participantMapper->save($participant);
        } elseif ($currentPlan->getParticipants()->count() > 1) {
            // Custom plan shared with another student
            $newPlan = clone $currentPlan;
            $newPlan->setId(null)
                    ->setName($currentPlan->getName() . ' Resubmit')
                    ->setCloseDate($possibleCloseDate);
            $this->planMapper->save($newPlan);

            $participant->setPlan($newPlan);
            $this->participantMapper->save($participant);
        } else {
            // Custom plan with this user only - Amending the close date
            $participant->getPlan()->setCloseDate($possibleCloseDate);
            $this->planMapper->save($participant->getPlan());
        }
    }


    /**
     * Post-save callback to check whether a user does not yet have a participation record, and inserts one in they dynamic plan.
     *
     * @param User             $student
     * @param Assessment\Model $assessment
     * @return bool
     * @throws PDOException
     * @throws SchoolboxMapper\Exception
     * @throws UnexpectedValueException
     * @throws SchoolboxMapper\DefinitionException
     * @throws InvalidState If we detect we've just put the system into an invalid state
     * @throws InvalidArgumentException If assessment not sufficiently hydrated
     */
    public function insertParticipationOnFirstInteraction(User $student, Assessment\Model $assessment): bool
    {
        $this->loadPlansAndParticipants($assessment);
        $participant = $this->findParticipation($student, $assessment);

        // Does user have a participation record?
        if ($participant) {
            if ($participant->isExcluded()) {
                // Did we find an exclusion record? You must not create interactions.
                throw new InvalidState("User ({$student->getId()}) has participation exclusion ({$participant->getId()})");
            }
        } else {
            // No participation yet. Is there a dynamic plan available to insert them into?
            $dynamicPlan = $assessment->getDynamicPlan();
            if (!$dynamicPlan) {
                throw new InvalidState("User ({$student->getId()}) has no participation, but assessment ({$assessment->getId()}) has no dynamic plan");
            }
            $participant = $this->participantMapper->createModel()
                                                   ->setUser($student)
                                                   ->setPlan($dynamicPlan);
            $this->participantMapper->save($participant);
            $dynamicPlan->getParticipants()[$student->getId()] = $participant;
        }
        return true;
    }


    /**
     * Load this assessment's plans and participants
     *
     * @param Assessment\Model $assessment
     * @throws SchoolboxMapper\DefinitionException
     * @throws InvalidArgumentException If assessment is not sufficiently hydrated
     * @throws SchoolboxMapper\Exception
     */
    private function loadPlansAndParticipants(Assessment\Model $assessment): void
    {
        if (null === $assessment->getId()) {
            throw new InvalidArgumentException('Assessment id not set');
        }
        $plans = new GenericCollection(Model::class);
        foreach ($this->planMapper->findPlansAndParticipantsForAssessments([$assessment->getId()]) as $plan) {
            $plan->setAssessment($assessment);
            $plans[$plan->getId()] = $plan;
        }
        $assessment->setPlans($plans);
    }


    /**
     * @param User             $student
     * @param Assessment\Model $assessment
     * @return null|Participant\Model
     */
    private function findParticipation(User $student, Assessment\Model $assessment): ?Participant\Model
    {
        foreach ($assessment->getPlans() as $plan) {
            foreach ($plan->getParticipants() as $participant) {
                // TODO A-4539 fix unreliable hydration type bug
                if ($participant->getUser()->getId() == $student->getId()) {
                    return $participant;
                }
            }
        }
        return null;
    }
}
