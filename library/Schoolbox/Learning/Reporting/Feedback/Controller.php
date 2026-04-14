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
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private ErrorMapper $errorMapper,
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
        $this->response = $this->responseFromTemplate('@learningReportingFeedback/index.twig', [
            'assessment' => $assessment,
            'learner' => $learner,
            'feedback' => $feedback,
        ], 'Feedback');
    }


    /**
     * Process the form vars
     */
    public function postExample(): void
    {
        try {
            $user = $this->userMapper->findById($this->requestPost['id']);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->response = $this->responseFromTemplate(
            '@learningReportingFeedback/index.twig',
            [
                'user' => $user,
                'var1' => $this->requestPost['var1'],
            ],
            'Example Form Output',
        );
    }
}
