<?php
/**
 * ResponsesOnly
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Timeline;

use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;

class ResponsesOnly extends Full
{
    /**
     * We only need Submitted Responses here
     * @inheritdoc
     */
    protected function recombine($includeUnpublishedFeedback, $includeDraftResponses, $minimumVisibility)
    {
        /** @var Submission $currentResponse */
        foreach ($this->getParticipant()->getAllResponses() as $currentResponse) {

            /* Only show Submitted submissions (ignore drafts)
             */
            if ($currentResponse->isSubmitted()) {
                $this->getRecombined()->add($currentResponse);
            }
        }

        /* Now sort them with a callback attack
         */
        $this->getRecombined()->sortBy('submittedAt', 'desc');
    }
}
