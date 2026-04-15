<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting\Feedback;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\User;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private BreadcrumbFactory $bakery,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private \Schoolbox\Assessment\Term\Mapper $termMapper,
        private StateMapper $stateMapper,
        private Participant\DataSource $participantDataSource,
        private IFeedbackDataSource $feedbackDataSource,
    ) {
        /* TODO Technical Uplift Candidate
         *  StateMapper.
         *  Submission States are currently in the Due Work folder, but should be promoted into a more global space.
         *  They are used among multiple learning activities: due work, quiz, reporting (at time of writing) as are the
         *  Mark Types stuff.
         */
    }


    /**
     * A redirect to the first participant in the set.
     * It doesn't care whether they already have a feedback or not.
     *
     * @param Reporting\Model $assessment
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function getFirstParticipantFeedback(Reporting\Model $assessment): void
    {
        $participants = $assessment->getAllParticipants()->filter(fn($participant) => !$participant->isExcluded());
        $firstParticipant = $participants->first()?->getUser();

        if ($firstParticipant instanceof User) {
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('learningReportingFeedback.getParticularFeedback', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'learnerId' => $firstParticipant->getId(),
                    'learner' => $firstParticipant,
                ])
            );

            return;
        }

        /* If the participant list is empty, nothing can be shown
         */
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * The screen that lets you provide feedback to a particular $learner
     *
     * @param Model $assessment
     * @param User $learner
     * @param Reporting\Feedback\Model|null $feedback
     * @throws Error
     * @throws RouteNotFound
     */
    public function getParticularFeedback(
        Reporting\Model $assessment,
        \Schoolbox\User $learner,
        ?Reporting\Feedback\Model $feedback = null,
    ): void {
        $participant = $assessment->getParticipant($learner);

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        /**
         * Generate the participant carousel, only if you have access to the status page
         */
        $statusRoute = $this->getRouteIfAuthorized('learningAssessment.getStatus', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
        ]);

        $participantCarousel = [];

        if ($statusRoute) {
            /** since this route is loaded for a specific participant, only that participant is hydrated by the module. we need the rest */
            $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment);
            foreach ($assessment->getAllParticipants() as $participant) {
                /* Are they excluded? ignore them */
                if ($participant->isExcluded()) {
                    continue;
                }

                $authoritativeFeedback = null;

                if ($participant->getAllFeedbacks()->count()) {
                    /** @var \Schoolbox\Learning\Assessment\Reporting\Feedback\Model|null $authoritativeFeedback */
                    $authoritativeFeedback = $assessment->getAuthoritativeFeedback($participant, true);
                }

                /* These now require the Status enum for the state of a feedback. In dialog agents this represented the
                 * state of a response, but we have no such scenario in reporting activities. The closest synonym seems
                 * to be the existence (and state) of a feedback
                 */
                $status = Reporting\Feedback\Status::INCOMPLETE;

                if ($feedback) {
                    if ($feedback->isDraft()) {
                        $status = Reporting\Feedback\Status::DRAFT;
                    } elseif ($feedback->isPublished()) {
                        $status = Reporting\Feedback\Status::PUBLISHED;
                    }
                }

                $participantCarousel[] = [
                    'id' => $participant->getUser()->getId(),
                    'value' => $this->getRouteIfAuthorized('learningReportingFeedback.getParticularFeedback', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId(),
                        'learner' => $participant->getUser(),
                        'learnerId' => $participant->getUser()->getId(),
                        'feedback' => $authoritativeFeedback,
                    ]),
                    'user' => (new User\Translator\Author\ModelToArray())->__invoke($participant->getUser()),
                    'status' => $status,
                    'statusLabel' => $status->translate(),
                ];
            }
        }

        $this->response = $this->responseFromTemplate('@learningReportingFeedback/index.twig', [
            'assessment' => $assessment,
            'learner' => $learner,
            'feedback' => $feedback,
            'interactionLocked' => $isInteractionLocked,
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) ])
                ->add(['Feedback', '']),
            'carousel' =>  (new \Schoolbox\Learning\Reporting\Feedback\StudentCarousel\Standard(
                $this->routeGenerator,
                $learner,
                $assessment
            )),
            /* Participant Carousel; note that this is aliased to "students" in the twig fragment
             */
            'participantCarousel' => $participantCarousel,
            /* For saving feedbacks
             */
            'errors' => $this->stateMapper->listDisplayValues(),
            'participant' => $participant,
        ], 'Feedback');
    }


    /**
     * Posts feedback for a particular person. If the $feedback existed already, update that with the new POST payload
     * or spawn a whole new $feedback instance and then persist that.
     *
     * @param Model $assessment
     * @param User $learner
     * @param Reporting\Feedback\Model|null $feedback
     * @return void
     * @throws \Exception
     */
    public function postParticularFeedback(
        Reporting\Model $assessment,
        \Schoolbox\User $learner,
        ?Reporting\Feedback\Model $feedback = null,
    ): void {
        /* Check that $learner is indeed a participant of $assessment
         */
        $participant = $assessment->getParticipantIfExtant($learner);

        /* Not a participant? Or, you're excluded:
         * 404, I suppose: you came here directly, or multiplayer schoolbox happened. The participant carousel we built
         * was meant to omit excluded participants.
         */
        if (null === $participant) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        if ($participant->isExcluded()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Check that the subobject exists: annoyingly, this is required
         */
        try {
            $reporting = $this->reportingMapper->findOneByColumn('assessment_id', $assessment->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* For some reason this couldn't be found: maybe multiplayer schoolbox where someone deleted it while you
             * were actively using it
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Check if $feedback is null; if so, we're spawning a whole new one for this person
         */
        if (null === $feedback) {
            $feedback = new Reporting\Feedback\Model();
        }

        /* Update props and persist it
         */
        $post = $this->requestPost->getArrayCopy();

        /** @var \Schoolbox\Learning\Assessment\Reporting\Feedback\Model $feedback */
        $feedback
            ->setAssessable($assessment)
            ->setReporting($reporting)
            ->setReviewee($learner)
            ->setReviewer($this->getUser())
            ->setComment($post['comment'] ?? '');

        /** Get mark schemes
         * - Instructor mark scheme is used to normalise what was inputted by the user
         * - Learner mark scheme is used when displaying the mark back to learners/parents
         */
        $instructorScheme = $assessment->getInstructorMarkScheme();
        $learnerScheme = $assessment->getMarkScheme();

        /* This human readable mark is given by the instructor, from which we derive the normalised mark
         */
        $instructorHumanReadableMark = $post['mark'] ?? '';

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            $normalisedMark = $instructorScheme->getNormalisedMark($instructorHumanReadableMark);

            /* Find out what the learner's version would have been and save that too
             * Arguably you could skip this derivation, only doing it if $instructor scheme was different to $learner scheme
             */
            $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $instructorHumanReadableMark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 */
                $code = $this->stateMapper->findOneByColumn('display_as', $instructorHumanReadableMark);

                $normalisedMark = $code->getNormaliseAs();

                /* There isn't a learner-specific Submission State: it's the same for both parties.
                 */
                $learnerHumanReadableMark = $instructorHumanReadableMark;
            }
        }

        $feedback
            ->setNormalisedMark($normalisedMark)
            ->setHumanReadableMark($learnerHumanReadableMark)
            ->setInstructorMark($instructorHumanReadableMark);

        /* This publish=1 param tuple is sent only when Publish And Continue is clicked
         */
        if (1 === (int)($post['publish'] ?? null)) {
            $feedback
                ->setPublishedBy($this->getUser()->getId())
                ->setPublishedAt(\Alaress_Date_Format::now());
        }

        $this->feedbackDataSource->save($feedback);

        /**
         * We need to retrieve the new plan below, but it won't have been hydrated into $participant yet
         * Force re-hydration so that we can get the actual plan
         */
        $this->participantDataSource->loadParticipationIntoAssessment(
            $assessment,
            $learner
        );

        $plan = $participant->getPlan();

        $data = [
            'success' => true,
            'message' => 'OK',
            'id'      => $feedback->getId(),
            /* these extra fields are currently used by the Markbook to keep things responsive */
            'normalisedMark'              => $feedback->getNormalisedMark(),
            'humanReadableMark'           => $feedback->getHumanReadableMark(),
            'humanReadableInstructorMark' => $feedback->getInstructorMark(),
            'plan' => [
                'isDynamic' => $plan->getIsDynamic(),
                'name' => $plan->getName(),
                'dueDate' => $plan->getDueDate()?->getShort() ?? null,
            ],
        ];

        $this->response = $this->responseAsJson($data);
    }
}
