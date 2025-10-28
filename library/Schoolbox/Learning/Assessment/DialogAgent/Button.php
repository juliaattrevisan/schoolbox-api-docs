<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Schoolbox\Learning\Assessment\Participant;

/**
 * haha
 *
 * andrew.carlton 44 minutes ago
   > Button logic is on the status of the authoritative
   >    + If it is submitted OR past close/dye date - view activity
   >    + If there is a response that is not yet submitted and does have a started at time - resume
   >    + If either no response yet or there is a response but no started at - begin
 *
 * Note that there's an operational assumption here;
 * creating a response means setting all other responses to Authoritative: 0
 * except for the one you just made. This enforces the "one authoritative response at all times"
 * but we have to do it in bidness logic everywhere a response spawns.
 *
 * Also there's a crontab that shuts unsubmitted responses once the assessment reaches its due/close date,
 * much like its quiz attempt counterpart that runs on Help.
 */
class Button
{
    /**
     * Words for the calls-to-action
     */
    private const string LABEL_START = 'Begin';
    private const string LABEL_RESUME = 'Resume';
    private const string LABEL_VIEW = 'View';


    /**
     * Takes a fully-hydrated $participant along with their $response instances and the assessment.
     * From those, decides what the call to action should be on the Summary screen.
     *
     * @param Model $assessment
     * @param Participant $participant
     * @return array
     */
    public function generate(Model $assessment, Participant $participant): array
    {
        $button = [
            'label' => null,
            'responseId' => null,
            'state' => null,
            'url' => null,  // hurmmm needs a Router, but do we want that here...
        ];

        /* If the assessment has surpassed its publish date, but not yet reached its open date,
         * button says "Begin" in a disabled state.
         */

        /* Otherwise it's reached its open date and we have to deal with responses somewhat intricately
         */

        /* If there are no responses at all, button says "Begin"
         * You're about to make the first one and the first one is always authoritative.
         */

        /* Otherwise, there are existing response(s); dig deeper.
         *
         * Look for an authoritative response. If there is one:
         *     - if it has a submitted_at, button says "View"
         *     - otherwise, button says "Resume"
         */

        /* If you've spent the allotted time across all of your responses, you can't make any more responses.
         * button says "View" so you can see the authoritative response.
         *
         * TODO depends on SA-21229 existing.
         */

        /* If the assessment has surpassed its due/close date,
         * and there are no authoritative responses,
         * button says "View" but in a disabled state.
         *
         * The student never did the work.
         */

        /* If the assessment has surpassed its due date,
         * but this is an authoritative response,
         * button says "View" but lets you continue so you can look at it.
         */
    }
}
