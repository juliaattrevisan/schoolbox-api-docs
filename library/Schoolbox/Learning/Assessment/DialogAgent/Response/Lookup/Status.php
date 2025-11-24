<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;

class Status
{
    /**
     * Consts correlating to available endpoints
     * @see /library/Schoolbox/Assessment/Types/dialogAgent.ts
     */
    public const string ACTION_VIEW = 'view';
    public const string ACTION_SOBO = 'sobo';
    public const string ACTION_RESET = 'reset';
    public const string ACTION_REOPEN = 'reopen';
    public const string ACTION_RESOLVE = 'resolve';


    /**
     * Correlate statuses with icons
     */
    public const array ICONS = [
        self::STATUS_INCOMPLETE => 'incomplete',
        self::STATUS_REQUIRES_ASSISTANCE => 'requires-assistance',
        self::STATUS_SUBMITTED => 'submitted',
        self::STATUS_IN_PROGRESS => 'in-progress',
    ];


    /**
     * Consts correlating to the status enum used by frontend world
     * Not to be confused with the progress tick classes, despite them currently being the same strings
     * @see /library/Schoolbox/Assessment/Types/dialogAgent.ts
     */
    public const string STATUS_INCOMPLETE = 'incomplete';
    public const string STATUS_IN_PROGRESS = 'in-progress';
    public const string STATUS_REQUIRES_ASSISTANCE = 'requires-assistance';
    public const string STATUS_SUBMITTED = 'submitted';


    /**
     * Decide the current status of a particular response and returns the various metadata
     * relevant per state which you will probably just arbitrarily render if extant.
     *
     * Returns a set of (status, meta array) tuples.
     *
     * @param ResponseModel|null $authoritativeResponse
     * @return array
     */
    public function calculate(?ResponseModel $authoritativeResponse): array
    {
        /* No responses? No trabajo
         *
         * If, for some reason, there isn't an authoritative response, I suppose we're Pending again.
         * This should never be the case and is meant to be enforced by business logic alone, but there may
         * be a mistake made by us, that lets students have multiple responses but none were authoritative.
         */
        if (null === $authoritativeResponse) {
            return [
                'status' => self::STATUS_INCOMPLETE,
                'actions' => [
                    self::ACTION_VIEW => false,
                    self::ACTION_SOBO => false,
                    self::ACTION_RESET => false,
                    self::ACTION_REOPEN => false,
                    self::ACTION_RESOLVE => false,
                ],
            ];
        }

        /* Submitted: disregard all other statuses
         */
        if (null !== $authoritativeResponse->getSubmittedAt()) {
            return [
                'status' => self::STATUS_SUBMITTED,
                'actions' => [
                    self::ACTION_VIEW => true,
                    self::ACTION_SOBO => false,
                    self::ACTION_RESET => false,
                    self::ACTION_REOPEN => true,
                    self::ACTION_RESOLVE => false,
                ],
            ];
        }

        /* A session identifier may or may not exist, not yet submitted: participant is still doing the stuff
         * and maybe needs help. Perhaps they need help before they even get to the point of having a session id.
         *
         * If assistance was requested and the response is still in progress, send a special status
         */
        if (null !== $authoritativeResponse->getAssistanceRequestedAt()) {
            return [
                'status' => self::STATUS_REQUIRES_ASSISTANCE,
                'actions' => [
                    self::ACTION_VIEW => true,
                    self::ACTION_SOBO => true,
                    self::ACTION_RESET => true,
                    self::ACTION_REOPEN => false,
                    self::ACTION_RESOLVE => true,
                ],
            ];
        }

        /* No session identifier yet; a draft exists but the participant hasn't sent their first message to ai services
         */
        if (null === $authoritativeResponse->getSessionId()) {
            return [
                'status' => self::STATUS_INCOMPLETE,
                'actions' => [
                    self::ACTION_VIEW => false,
                    self::ACTION_SOBO => false,
                    self::ACTION_RESET => false,
                    self::ACTION_REOPEN => false,
                    self::ACTION_RESOLVE => false,
                ],
            ];
        }

        /* Otherwise we're just doing the stuff
         */
        return [
            'status' => self::STATUS_IN_PROGRESS,
            'actions' => [
                self::ACTION_VIEW => true,
                self::ACTION_SOBO => true,
                self::ACTION_RESET => true,
                self::ACTION_REOPEN => false,
                self::ACTION_RESOLVE => false,
            ],
        ];
    }
}
