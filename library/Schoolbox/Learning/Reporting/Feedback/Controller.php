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
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Status;
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
        private Participant\DataSource $participantDataSource,
    ) {
    }


    /**
     * A redirect to the first participant who had a feedback
     *
     * @param Reporting\Model $assessment
     * @throws RouteNotFound
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
     * The screen that lets you provide feedback to a particular student
     *
     * @param Model $assessment
     * @param User $learner
     * @param Reporting\Feedback\Model|null $feedback
     * @throws Error
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

                $status = $feedback ?? Status::INCOMPLETE;

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
                ->add(['Inline Grader', '']),
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
            'errors' => StateMapper::getInstance()->listDisplayValues(),
            'participant' => $participant,
        ], 'Feedback');
    }
}
