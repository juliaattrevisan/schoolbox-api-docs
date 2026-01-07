<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;

class NextRoutes
{
    /**
     * Consts correlating to available endpoints
     * @see /library/Schoolbox/Assessment/Types/dialogAgent.ts
     */
    public const string ACTION_VIEW = 'view';
    public const string ACTION_MESSAGE = 'message';
    public const string ACTION_SUBMIT = 'submit';
    public const string ACTION_RESET = 'reset';
    public const string ACTION_REOPEN = 'reopen';
    public const string ACTION_ASSISTANCE_REQUEST = 'assistanceRequest';
    public const string ACTION_ASSISTANCE_RESOLVE = 'assistanceResolve';


    /**
     * Decide the next available routes for a particular response; these are used in a Module check to see if
     * you can get to an endpoint whilst $authoritativeResponse is in a particular state.
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
                self::ACTION_VIEW => true,
                self::ACTION_MESSAGE => false,
                self::ACTION_SUBMIT => false,
                self::ACTION_RESET => false,
                self::ACTION_REOPEN => false,
                self::ACTION_ASSISTANCE_REQUEST => false,
                self::ACTION_ASSISTANCE_RESOLVE => false,
            ];
        }

        /* Submitted: disregard all other statuses
         */
        if (null !== $authoritativeResponse->getSubmittedAt()) {
            return [
                self::ACTION_VIEW => true,
                self::ACTION_MESSAGE => false,
                self::ACTION_SUBMIT => false,
                self::ACTION_RESET => false,
                self::ACTION_REOPEN => true,
                self::ACTION_ASSISTANCE_REQUEST => false,
                self::ACTION_ASSISTANCE_RESOLVE => false,
            ];
        }

        /** @var DialogAgent $assessment */
        $assessment = $authoritativeResponse->getAssessable();

        /* The response is in progress
         */
        return [
            self::ACTION_VIEW => true,
            /* You can keep sending messages until the duration has been exceeded (if any),
             * after that, the response is either submitted, or about to be submitted by the frontend/SOBO/the cron later on
             */
            self::ACTION_MESSAGE =>
                null === $authoritativeResponse->getStartedAt()
                || $assessment->isDurationUnlimited()
                || $authoritativeResponse->getTimeTaken() < $assessment->getDuration(),
            /* You can submit or reset only once the attempt has actually started
             * (student sent the first message to AI so a session id exists).
             *
             * Duration does not block these as to ensure a delayed submission is always possible
             * (SOBO/the student left the page/the browser was slow at submitting/etc).
             */
            self::ACTION_SUBMIT => null !== $authoritativeResponse->getSessionId(),
            self::ACTION_RESET => null !== $authoritativeResponse->getSessionId(),
            /* reopen is only possible whilst in progress */
            self::ACTION_REOPEN => false,
            /* A session identifier may or may not exist, not yet submitted: participant is still doing the stuff
             * and maybe needs help. Perhaps they need help before they even get to the point of having a session id.
             *
             * If assistance was requested and the response is still in progress, send a special status
             */
            self::ACTION_ASSISTANCE_REQUEST => null === $authoritativeResponse->getAssistanceRequestedAt(),
            self::ACTION_ASSISTANCE_RESOLVE => null !== $authoritativeResponse->getAssistanceRequestedAt(),
        ];
    }
}
