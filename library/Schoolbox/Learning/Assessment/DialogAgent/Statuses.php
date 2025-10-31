<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Schoolbox\Learning\Assessment\Participant;

class Statuses
{
    private const string STATUS_PENDING = 'Pending';
    private const string STATUS_IN_PROGRESS = 'In Progress';
    private const string STATUS_REQUESTED_ASSISTANCE = 'Requires Assistance';
    private const string STATUS_SUBMITTED = 'Submitted';


    /**
     * Constructor.
     */
    public function __construct()
    {
    }


    /**
     * Returns a set of (user id, status) tuples for a given assessment
     *
     * @param Model $assessment  A fully hydrated assessment with participants and responses etc.
     * @return array
     */
    public function generate(Model $assessment): array
    {
        $stack = [];

        foreach ($assessment->getAllParticipants() as $participant) {
            $stack[$participant->getUser()->getId()] = $this->calculate($assessment, $participant);
        }

        return $stack;
    }


    /**
     * Decide the current status of a $participant's response and returns that along with the various metadata
     * relevant per state which you will probably just arbitrarily render if extant.
     *
     * Returns a set of (status, meta array) tuples.
     *
     * @param Model $assessment
     * @param Participant $participant
     * @return array
     */
    private function calculate(Model $assessment, Participant $participant): array
    {
        /* No responses? No trabajo
         */
        if (!$participant->getAllResponses()->count()) {
            return [
                'status' => self::STATUS_PENDING,
            ];
        }

        /* Find an authoritative response for this participant
         */
        /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model $authoritativeResponse */
        $authoritativeResponse = $assessment->getAuthoritativeResponse($participant, true);

        /* If, for some reason, there isn't an authoritative response, I suppose we're Pending again.
         * This should never be the case and is meant to be enforced by business logic alone, but there may
         * be a mistake made by us, that lets students have multiple responses but none were authoritative.
         */
        if (null === $authoritativeResponse) {
            return [
                'status' => self::STATUS_PENDING,
            ];
        }

        /* No session identifier yet; participant hasn't sent their first message to ai services
         */
        if (null === $authoritativeResponse->getSessionId()) {
            return [
                'status' => self::STATUS_PENDING,
            ];
        }

        /* A session identifier exists but not yet submitted: participant is still doing the stuff
         * and maybe needs help
         */
        if (null === $authoritativeResponse->getSubmittedAt()) {
            /* If assistance was requested and the response is still in progress, send a special status
             */
            if (null !== $authoritativeResponse->getAssistanceRequestedAt()) {
                return [
                    'status' => self::STATUS_REQUESTED_ASSISTANCE,
                    'assistanceDate' => $authoritativeResponse->getAssistanceRequestedAt()->getLocal(),
                ];
            }

            /* Otherwise we're just doing the stuff
             */
            return [
                'status' => self::STATUS_IN_PROGRESS,
            ];
        }

        /* Submitted: disregard all other statuses
         */
        if (null !== $authoritativeResponse->getSubmittedAt()) {
            return [
                'status' => self::STATUS_SUBMITTED,
                'submittedAt' => $authoritativeResponse->getSubmittedAt()->getLocal(),
                'submittedBy' => $authoritativeResponse->getSubmittedBy()->formatName(),
            ];
        }

        /* Unforeseen circumstance: not sure how we'd end up here
         */
        return [
            'status' => null,
        ];
    }
}
