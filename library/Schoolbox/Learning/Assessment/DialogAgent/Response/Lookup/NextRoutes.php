<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;

class NextRoutes
{
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
                self::ACTION_VIEW => false,
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

        // TODO: deal with duration eventually. still allow SOBO from staff

        /* A session identifier may or may not exist, not yet submitted: participant is still doing the stuff
         * and maybe needs help. Perhaps they need help before they even get to the point of having a session id.
         *
         * If assistance was requested and the response is still in progress, send a special status
         */
        if (null !== $authoritativeResponse->getAssistanceRequestedAt()) {
            return [
                self::ACTION_VIEW => true,
                self::ACTION_MESSAGE => true,
                // TODO: A student could request assistance prior to having a session ID,
                // which would then turn ON the SUBMIT/RESET options... I doubt we want that...
                self::ACTION_SUBMIT => true,
                self::ACTION_RESET => true,
                self::ACTION_REOPEN => false,
                self::ACTION_ASSISTANCE_REQUEST => false,
                self::ACTION_ASSISTANCE_RESOLVE => true,
            ];
        }

        /* No session identifier yet; a draft exists but the participant hasn't sent their first message to ai services
         */
        if (null === $authoritativeResponse->getSessionId()) {
            return [
                self::ACTION_VIEW => true,
                self::ACTION_MESSAGE => true,
                self::ACTION_SUBMIT => false,
                self::ACTION_RESET => false,
                self::ACTION_REOPEN => false,
                self::ACTION_ASSISTANCE_REQUEST => true,
                self::ACTION_ASSISTANCE_RESOLVE => false,
            ];
        }

        /* Otherwise we're just doing the stuff
         */
        return [
            self::ACTION_VIEW => true,
            self::ACTION_MESSAGE => true,
            self::ACTION_SUBMIT => true,
            self::ACTION_RESET => true,
            self::ACTION_REOPEN => false,
            self::ACTION_ASSISTANCE_REQUEST => true,
            self::ACTION_ASSISTANCE_RESOLVE => false,
        ];
    }
}
