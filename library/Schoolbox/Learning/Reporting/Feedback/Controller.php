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
        private \Schoolbox\Assessment\Term\Mapper $termMapper,
        private StateMapper $stateMapper,
        private Participant\DataSource $participantDataSource,
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

        // TODO Check if $feedback is null; if so, we're spawning a whole new one for this person

        // TODO Otherwise, update $feedback's properties

//        $feedback = new \Schoolbox\Learning\Assessment\Reporting\Feedback\Model()
//            ->setAssessable($assessmentMapperProxy->findById(1688))
//            ->setReporting($reportingMapper->findByid(2))
//            ->setReviewee($userMapper->findByid(20))
//            ->setReviewer($userMapper->findByid(2))
//            ->setNormalisedMark(99)
//            ->setHumanReadableMark('14 / 117')
//            ->setInstructorMark('Emerging')
//            ->setComment('moshae why')
//            ->setPublishedBy($userMapper->findById(2)->getId())
//            ->setPublishedAt(\Alaress_Date_Format::now());

        // TODO Save it
    }
}
