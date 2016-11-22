<?php
/**
 * Created by PhpStorm.
 * User: andy
 * Date: 10/10/16
 * Time: 10:25 AM
 */

namespace Schoolbox\Learning\Assessment\FeedbackStrategy;


use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

class FavourFloating implements IFeedbackTraversalStrategy
{
    /**
     * Find the most recent IFeedback for the Participant
     *
     * Floating Feedback trumps linked Feedback
     *
     * Consider following scenario for DueWork.
     * * Student submits $response1 in 2010 and receives $feedback1 on it in 2014.
     * * Student submits $response2 in 2011 and receives $feedback2a on it in 2011 and $feedback2b in 2012
     * * Student submits $response3 in 2013
     * * Teacher provides floating $feedback4 in 2010
     *
     * In this case, the authoritative feedback is the floating $feedback in 2010,
     * even though is predates the linked $feedback2b of 2012 and linked $feedback1 pf 2014.
     *
     * If no floating feedback exists, we would fall back to the latest feedback on the latest
     * response, ie $feedback2b. Even though it predates $feedback1.
     *
     * Need to also apply the $publishedOnly filter to all traversals.
     * @param Participant $participant
     * @param bool $publishedOnly
     * @return IFeedback|null
     */
    public function getMostRecentFeedback(Participant $participant, $publishedOnly)
    {
        /** @var IFeedback $mostRecentFloatingFeedback */
        $mostRecentFloatingFeedback = null;

        foreach ($participant->getAllFeedbacks() as $feedback) {
            if (!$publishedOnly || $feedback->isPublished()) {
                if(!$feedback->getResponse() instanceof IResponse){
                    if (!$mostRecentFloatingFeedback || $feedback->getUpdatedAt()->gt($mostRecentFloatingFeedback->getUpdatedAt())) {
                        $mostRecentFloatingFeedback = $feedback;
                    }
                }
            }
        }

        if($mostRecentFloatingFeedback){
            return $mostRecentFloatingFeedback;
        }

        /** @var IResponse $latestResponseWithFeedback */
        $latestResponseWithFeedback = null;

        foreach($participant->getAllResponses() as $response){
            if(!$latestResponseWithFeedback || $response->getUpdatedAt()->gt($latestResponseWithFeedback->getUpdatedAt())){
                if(!$publishedOnly){
                    if(count($response->getAllFeedback())){
                        $latestResponseWithFeedback = $response;
                    }
                } else {
                    foreach($response->getAllFeedback() as $feedback){
                        if($feedback->isPublished()){
                            $latestResponseWithFeedback = $response;
                            break;
                        }
                    }
                }
            }
        }

        // No Response with Feedback?
        if(!$latestResponseWithFeedback){
            return null;
        }

        /** @var IFeedback $mostRecentLinkedFeedback */
        $mostRecentLinkedFeedback = null;

        foreach($latestResponseWithFeedback->getAllFeedback() as $feedback){
            if (!$publishedOnly || $feedback->isPublished()) {
                if (!$mostRecentLinkedFeedback || $feedback->getUpdatedAt()->gt($mostRecentLinkedFeedback->getUpdatedAt())) {
                    $mostRecentLinkedFeedback = $feedback;
                }
            }
        }
        return $mostRecentLinkedFeedback;
    }

    /**
     * Find the most recent IFeedback published and visible for the Participant
     *
     * @param Participant $participant
     * @param User $viewingUser
     * @return IFeedback|null
     */
    public function getMostRecentVisibleFeedback(Participant $participant, User $viewingUser)
    {
        if ($participant->getAssessable()->isFeedbackVisibleToUser($viewingUser)) {
            return $this->getMostRecentFeedback($participant, true);
        } else {
            return null;
        }
    }
}
