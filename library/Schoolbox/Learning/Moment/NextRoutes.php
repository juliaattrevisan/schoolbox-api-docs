<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Moment;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Moment\Moderation\ModerationRequired;
use Schoolbox\User;

class NextRoutes
{
    /**
     * Return a _links array which contains urls for things you could potentially do
     * next, for a given $moment, for the Cohort Overview.
     *
     * This function now provides _links for students as well as other user roles.
     * It checks the current user's roletype if student, checks for moderation requirements.
     * The _links node is always included in the response of a PUT endpoint reflecting the users available actions.
     *
     * You have to actually obtain the links via the route generator in controllers
     * because it depends on a pre-existing logic that we shouldn't replicate here.
     * There doesn't seem to be a convenient way to compartmentalise getRouteIfAuthorized()
     *
     * @param Model $moment
     * @param User $user
     * @param bool $moderationRequired
     * @return array
     */
    public function generate(\Schoolbox\Learning\Moment\Model $moment, User $user, bool $moderationRequired): array
    {
        /**  states of a learning moment
         * draft
         * submitted
         * published
         * rejected
         */

        $isModerationRequired = false;

        //if user is student (and has mod)
        if ($user->getRole()->isStudent()) {
            $isModerationRequired = (new ModerationRequired())->isSatisfiedBy($moderationRequired, $user->getYear());
        }

        switch ($moment->getCurrentState()) {
            case \Schoolbox\Learning\Moment\Model::STATUS_PUBLISHED:
                /* Next mutable states: rejected
                 */
                return [
                    'submit' => null,
                    'publish' => null,
                    'reject' => $isModerationRequired ? null : true, // if you are being moderated, you can't reject your work
                ];
            case \Schoolbox\Learning\Moment\Model::STATUS_SUBMITTED:
                /* Next mutable states: published, rejected
                 */
                return [
                    'submit' => null,
                    'publish' => $isModerationRequired ? null : true, // if you are being moderated, you can't publish
                    'reject' => $isModerationRequired ? null : true, // if you are being moderated, you can't reject
                ];
            case \Schoolbox\Learning\Moment\Model::STATUS_REJECTED:
                /* Next mutable states: published
                 */
                return [
                    'submit' => null,
                    'publish' => $isModerationRequired ? null : true, // if you are being moderated, you can't publish / approve your work
                    'reject' => null,
                ];
            case \Schoolbox\Learning\Moment\Model::STATUS_DRAFT:
            default:
                /**
                 * students who require moderation, can only submit, and NOT publish
                 * all other users (staff, parents, and students (who are not being moderated)) can ship straight to publish and skip the submit route
                 */
                return [
                    'submit' => $isModerationRequired ? true : null,
                    'publish' => $isModerationRequired ? null : true,
                    'reject' => null,
                ];
        }
    }
}
