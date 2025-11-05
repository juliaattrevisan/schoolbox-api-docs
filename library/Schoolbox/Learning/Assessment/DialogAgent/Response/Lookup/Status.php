<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

use Schoolbox\Learning\Assessment\DialogAgent\Model;
use Schoolbox\Learning\Assessment\Participant;

class Status
{
    /**
     * Consts correlating to a nice phrase to say in frontend world
     */
    private const string STATUS_PENDING = 'Pending';
    private const string STATUS_IN_PROGRESS = 'In Progress';
    private const string STATUS_REQUIRES_ASSISTANCE = 'Requires Assistance';
    private const string STATUS_SUBMITTED = 'Submitted';


    /**
     * Consts correlating to progress tick cosmetics, as defined in _content.scss where I've already left
     * a note for snake to please refactor these styles in some reptilian manner
     *
     * Note that CSS_PENDING is '' because that defaults to an empty grey cube
     */
    private const string CSS_PENDING = '';
    private const string CSS_IN_PROGRESS = 'in-progress';
    private const string CSS_REQUIRES_ASSISTANCE = 'requires-assistance';
    private const string CSS_SUBMITTED = 'submitted';


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
        /* The "length" node indicates whether there was at least one action available.
         *
         * This is used to decide whether to render a tot menu to prevent you rendering a tot button
         * that had no actions inside the tot menu. You could infer this from Pending Status as an
         * alternative, but you'd need to hardcode the states in frontend to do that.
         */

        /* No responses? No trabajo
         */
        if (!$participant->getAllResponses()->count()) {
            return [
                'status' => self::STATUS_PENDING,
                'css' => self::CSS_PENDING,
                'response' => null,
                'actions' => [
                    'length' => 0,
                    'view' => false,
                    'sobo' => false,
                    'reset' => false,
                    'reopen' => false,
                    'resolved' => false
                ],
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
                'css' => self::CSS_PENDING,
                'response' => null,
                'actions' => [
                    'length' => 0,
                    'view' => false,
                    'sobo' => false,
                    'reset' => false,
                    'reopen' => false,
                    'resolved' => false,
                ],
            ];
        }

        /* Submitted: disregard all other statuses
         */
        if (null !== $authoritativeResponse->getSubmittedAt()) {
            return [
                'status' => self::STATUS_SUBMITTED,
                'css' => self::CSS_SUBMITTED,
                'response' => $authoritativeResponse,
                'meta' => [
                    'submittedAt' => [
                        'local' => $authoritativeResponse->getSubmittedAt()->getLocal(),
                        'rfc' => $authoritativeResponse->getSubmittedAt()->getUsingFormat(\Alaress_Date_Format::FORMAT_RFC3339_W3C),
                    ],
                    'submittedBy' => $authoritativeResponse->getSubmittedBy()->formatName(),
                ],
                'actions' => [
                    'length' => 1,
                    'view' => true,
                    'sobo' => false,
                    'reset' => false,
                    'reopen' => true,
                    'resolved' => false,
                ],
            ];
        }

        /* No session identifier yet; participant hasn't sent their first message to ai services
         */
        if (null === $authoritativeResponse->getSessionId()) {
            return [
                'status' => self::STATUS_PENDING,
                'css' => self::CSS_PENDING,
                'response' => null,
                'actions' => [
                    'length' => 0,
                    'view' => false,
                    'sobo' => false,
                    'reset' => false,
                    'reopen' => false,
                    'resolved' => false,
                ],
            ];
        }

        /* A session identifier exists but not yet submitted: participant is still doing the stuff
         * and maybe needs help
         *
         * If assistance was requested and the response is still in progress, send a special status
         */
        if (null !== $authoritativeResponse->getAssistanceRequestedAt()) {
            return [
                'status' => self::STATUS_REQUIRES_ASSISTANCE,
                'css' => self::CSS_REQUIRES_ASSISTANCE,
                'response' => $authoritativeResponse,
                'meta' => [
                    'assistanceRequestedAt' => [
                        'local' => $authoritativeResponse->getAssistanceRequestedAt()->getLocal(),
                        'rfc' => $authoritativeResponse->getAssistanceRequestedAt()->getUsingFormat(\Alaress_Date_Format::FORMAT_RFC3339_W3C),
                    ],
                ],
                'actions' => [
                    'length' => 1,
                    'view' => true,
                    'sobo' => true,
                    'reset' => true,
                    'reopen' => false,
                    'resolved' => true,
                ],
            ];
        }

        /* Otherwise we're just doing the stuff
         */
        return [
            'status' => self::STATUS_IN_PROGRESS,
            'css' => self::CSS_IN_PROGRESS,
            'response' => $authoritativeResponse,
            'actions' => [
                'length' => 1,
                'view' => true,
                'sobo' => true,
                'reset' => true,
                'reopen' => false,
                'resolved' => false,
            ],
        ];
    }
}
