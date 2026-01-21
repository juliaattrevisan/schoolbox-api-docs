<?php

/**
 * NextRoutesTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup\NextRoutes;
use Schoolbox\Learning\Assessment\DialogAgent\Response;

/**
 * NextRoutes test
 */
class NextRoutesTest extends TestCase
{
    /**
     * @param mixed $input
     * @param array $expected
     */
    #[DataProvider('providerNextRoutes')]
    public function testNextRoutes($input, $expected)
    {
        $this->assertEquals($expected, (new NextRoutes())->calculate($input));
    }

    /**
     * Data provider for testNextRoutes
     *
     * @return array
     */
    public static function providerNextRoutes(): array
    {
        return [
            /* You can get to the getParticularResponse route when you enter a weird state such that there are
             * no authoritative responses. We're not really sure how you could get into this state organically.
             * But, you can't do anything else other than get to one destination.
             */
            'null response' => [
                null,
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => false,
                    NextRoutes::ACTION_SUBMIT => false,
                    NextRoutes::ACTION_RESET => false,
                    NextRoutes::ACTION_REOPEN => false,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            /**
             * Unlimited duration
             */
            /* When the learner first starts up they can:
             *   - view it
             *   - post their first message to the ai
             *   - request an instructor's help
             *
             * Every other ability is disallowed
             */
            'in progress, unlimited duration, nothing posted yet' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(0))
                    ->setStartedAt(null)
                    ->setSessionId(null)
                    ->setAssistanceRequestedAt(null)
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    NextRoutes::ACTION_SUBMIT => false,
                    NextRoutes::ACTION_RESET => false,
                    NextRoutes::ACTION_REOPEN => false,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => true,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            /* When the learner posts their first message, they can:
             *   - view it
             *   - post another message
             *   - request an instructor's help
             *
             * Every other ability is disallowed
             */
            'in progress, unlimited duration, posted their first message' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(0))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(null)
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => true,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            'in progress, unlimited duration, assistance requested' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(0))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(new \Alaress_Date_Format('2026-01-20 01:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    /* You can't re-request a help, but you can answer the call of Gondor and resolve a help request
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => true,
                ],
            ],
            /**
             * Limited duration (10 seconds)
             */
            /* When the learner first starts up they can:
             *   - view it
             *   - post their first message to the ai
             *   - request an instructor's help
             *
             * Every other ability is disallowed
             */
            'in progress, limited duration, nothing posted yet' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(10))
                    ->setStartedAt(null)
                    ->setSessionId(null)
                    ->setAssistanceRequestedAt(null)
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    NextRoutes::ACTION_SUBMIT => false,
                    NextRoutes::ACTION_RESET => false,
                    NextRoutes::ACTION_REOPEN => false,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => true,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            /* When the learner posts their first message, they can:
             *   - view it
             *   - post another message
             *   - request an instructor's help
             *
             * Every other ability is disallowed
             */
            'in progress, limited duration, posted their first message' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(10))
                    /* Message first posted after 1 second elapsed
                     */
                    ->setStartedAt(\Alaress_Date_Format::now()->addSeconds(-9))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(null)
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => true,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            'in progress, limited duration, assistance requested' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(10))
                    /* Message first posted after 1 second elapsed
                     */
                    ->setStartedAt(\Alaress_Date_Format::now()->addSeconds(-9))
                    ->setSessionId('abcdefgh123456789')
                    /* Help requested by learner after 2 seconds elapsed
                     */
                    ->setAssistanceRequestedAt(\Alaress_Date_Format::now()->addSeconds(-8))
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => true,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    /* You can't re-request a help, but you can answer the call of Gondor and resolve a help request
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => true,
                ],
            ],
            /* Where duration actually matters: posting a message when your time is up
             * This is coded to test now(), but I've put startedAt well into the past so it's definitely exceeded duration
             */
            'in progress, limited duration exceeded, message attempted' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(10))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(null)
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    /* You can't make any more messages
                     */
                    NextRoutes::ACTION_MESSAGE => false,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    /* You can still ask for help even if your time is up: you might need help actually submitting
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => true,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            'in progress, limited duration exceeded, help requested' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(10))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(new \Alaress_Date_Format('2026-01-20 01:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    /* You can't make any more messages
                     */
                    NextRoutes::ACTION_MESSAGE => false,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    /* You can't re-request a help, but you can answer the call of Gondor and resolve a help request
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => true,
                ],
            ],
            /* Responses need to have been started before they can be submitted;
             * Responses already submitted cannot be resubmitted unless they are first reopened.
             */
            'ready to submit' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(0))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(new \Alaress_Date_Format('2026-01-20 01:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSubmittedAt(null),
                [
                    NextRoutes::ACTION_VIEW => true,
                    /* You can't make any more messages
                     */
                    NextRoutes::ACTION_MESSAGE => true,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => true,
                    /* Once the first message has been posted, this become resettable
                     */
                    NextRoutes::ACTION_RESET => true,
                    NextRoutes::ACTION_REOPEN => false,
                    /* You can't re-request a help, but you can answer the call of Gondor and resolve a help request
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => true,
                ],
            ],
            'already submitted' => [
                (new Response\Model())
                    ->setAssessable((new DialogAgent\Model())->setDuration(0))
                    ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSessionId('abcdefgh123456789')
                    ->setAssistanceRequestedAt(new \Alaress_Date_Format('2026-01-20 01:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
                    ->setSubmittedAt(new \Alaress_Date_Format('2026-01-20 01:07:03', \Alaress_Date_Format::DATABASE_FORMAT)),
                [
                    NextRoutes::ACTION_VIEW => true,
                    /* You can't make any more messages
                     */
                    NextRoutes::ACTION_MESSAGE => false,
                    /* Once the first message has been posted, this becomes submittable
                     */
                    NextRoutes::ACTION_SUBMIT => false,
                    /* Once submitted, you can't reset it: you have to put it back into In Progress first, via reopen
                     */
                    NextRoutes::ACTION_RESET => false,
                    /* Once it's been submitted, it can be reopened
                     */
                    NextRoutes::ACTION_REOPEN => true,
                    /* Once submitted, you can't request help, nor can you answer the call of Gondor
                     */
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
            /* A submitted response allows someone to:
             *   - view it
             *   - reopen ("unsubmit") it
             */
            'submitted' => [
                (new Response\Model())->setSubmittedAt(\Alaress_Date_Format::now()),
                [
                    NextRoutes::ACTION_VIEW => true,
                    NextRoutes::ACTION_MESSAGE => false,
                    NextRoutes::ACTION_SUBMIT => false,
                    NextRoutes::ACTION_RESET => false,
                    NextRoutes::ACTION_REOPEN => true,
                    NextRoutes::ACTION_ASSISTANCE_REQUEST => false,
                    NextRoutes::ACTION_ASSISTANCE_RESOLVE => false,
                ],
            ],
        ];
    }
}
