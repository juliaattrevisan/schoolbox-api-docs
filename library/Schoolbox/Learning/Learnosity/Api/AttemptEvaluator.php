<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Api;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Attempt;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\User;

/**
 * API for evaluating attempts, and for initiating polling of Learnosity for
 * whether an attempt has been scored.
 */
class AttemptEvaluator
{
    /**
     * Constructor
     *
     * @param UriInterface            $baseUrl
     * @param RequestFactoryInterface $httpFactory
     * @param Generator               $routeGenerator
     * @param MessageFactory          $messageFactory
     * @param Producer                $messageProducer
     */
    public function __construct(
        private UriInterface $baseUrl,
        private RequestFactoryInterface $httpFactory,
        private Generator $routeGenerator,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
    ) {
    }


    /**
     * Start polling for this attempt's score.
     *
     * This will set up a task daemon job, which will call
     * `\Schoolbox\Learning\Learnosity\Helper::processAttemptCompletion`
     * (via a controller) repeatedly until that method reports it's done.
     *
     * @param User $user
     * @param QuizDomainModel $assessment
     * @param Model $existingAttempt
     * @return void
     * @throws RouteNotFound
     * @throws Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws \InvalidArgumentException
     */
    public function poll(
        User $user,
        QuizDomainModel $assessment,
        Attempt\Model $existingAttempt
    ): void {
        /** @noinspection PhpUnhandledExceptionInspection - this route exists */
        $route = $this->routeGenerator->generate(
            'learnosity.postPollQuizAttemptStatus',
            [
                'assessmentId'               => $assessment->getId(),
                'studentId'                  => $existingAttempt->getUser()->getId(),
                'learnositySessionReference' => $existingAttempt->getLearnosityReference(),
            ]
        );

        /* Update schoolbox when we're done
         */
        $callbackUrl = $this->routeGenerator->generate(
            'learningQuizAttempt.postRescore',
            [
                'assessmentId'               => $assessment->getId(),
                'studentId'                  => $existingAttempt->getUser()->getId(),
                'learnositySessionReference' => $existingAttempt->getLearnosityReference(),
            ]
        );

        $message = $this->messageFactory
            ->setUser($user)
            ->setRequest(
                $this->httpFactory
                    ->createRequest(
                        'POST',
                        $this->baseUrl->withPath($route),
                    )
                    ->withHeader('accept', 'application/json')
            )
            ->setCallbackUrl((string)$this->baseUrl->withPath($callbackUrl))
            ->build();

        $this->messageProducer->send(
            $message,
            delay: 5,
            maxRequeues: Helper::LEARNOSITY_ATTEMPT_COMPLETION_MAX_POLLS,
        );
    }
}
