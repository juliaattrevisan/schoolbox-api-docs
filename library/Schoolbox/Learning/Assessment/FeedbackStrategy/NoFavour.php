<?php

/**
 * Created by PhpStorm.
 * User: andy
 * Date: 10/10/16
 * Time: 10:26 AM
 */

namespace Schoolbox\Learning\Assessment\FeedbackStrategy;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;

/**
 * @deprecated Logic has been absorbed
 * @see        Participant
 */
class NoFavour implements IFeedbackTraversalStrategy
{
    /**
     * Find the most recent IFeedback for the Participant
     *
     * All Feedbacks are considered equal, compared purely by dates
     *
     * Consider following scenario for DueWork.
     * * Student submits $response1 in 2010 and receives $feedback1 on it in 2014.
     * * Student submits $response2 in 2011 and receives $feedback2a on it in 2011 and $feedback2b in 2012
     * * Student submits $response3 in 2013
     * * Teacher provides floating $feedback4 in 2010
     *
     * In this case, the authoritative feedback is the linked $feedback1 in 2014,
     * even though it is attached to an old response and we have an overall floating $feedback4 in 2010
     *
     * Need to also apply the $publishedOnly filter to all traversals.
     *
     * @param Participant $participant
     * @param bool $publishedOnly
     * @return IFeedback|null
     */
    public function getMostRecentFeedback(Participant $participant, $publishedOnly)
    {
        /** @var IFeedback $mostRecentFeedback */
        $mostRecentFeedback = null;

        foreach ($participant->getAllFeedbacks() as $feedback) {
            if (!$publishedOnly || $feedback->isPublished()) {
                if (!$mostRecentFeedback || $feedback->getUpdatedAt()->gt($mostRecentFeedback->getUpdatedAt())) {
                    $mostRecentFeedback = $feedback;
                }
            }
        }
        return $mostRecentFeedback;
    }
}
