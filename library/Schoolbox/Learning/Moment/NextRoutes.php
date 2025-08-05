<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Moment;

class NextRoutes
{
    /**
     * Return a _links array which contains urls for things you could potentially do
     * next, for a given $moment, for the Cohort Overview.
     *
     * You could expand this to provide _links based on the current user's roletype,
     * check for additional perms and moderation steps etc. if you wanted this to
     * also surface for students (so far that hasn't been needed) because this _links
     * node is always in the response of a PUT endpoint. It'll just be all nulls for
     * students, for now.
     *
     * You have to actually obtain the links via the route generator in controllers
     * because it depends on a pre-existing logic that we shouldn't replicate here.
     * There doesn't seem to be a convenient way to compartmentalise getRouteIfAuthorized()
     *
     * @param Evidence\Model $moment
     * @return array
     */
    public function generate(Evidence\Model $moment): array
    {
        /* You can't SOBO a learning moment, so this is always null for now:
         * That might change later
         */
        $submitLink = null;

        if ($moment->isSubmitted()) {
            /* Next mutable states: published, rejected
             */
            $publishLink = true;
            $rejectLink = true;
        } elseif ($moment->isPublished()) {
            /* Next mutable states: rejected
             */
            $publishLink = null;
            $rejectLink = true;
        } elseif ($moment->isRejected()) {
            /* Next mutable states: published
             */
            $publishLink = true;
            $rejectLink = null;
        } else {
            /* Next mutable states: published, rejected
             */
            $publishLink = true;
            $rejectLink = true;
        }

        return [
            'submit' => $submitLink,
            'publish' => $publishLink,
            'reject' => $rejectLink,
        ];
    }
}
