<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

/**
 * Observer able to interrogate an assessment and provide statistical information.
 */
class Statistics
{
    /**
     * @var IAssessable
     */
    private $assessment;


    /**
     * @param IAssessable $assessment
     */
    public function __construct(IAssessable $assessment)
    {
        $this->assessment = $assessment;
    }


    /**
     * Calculate how many Participant instances are in this IAssessable
     *
     * @return int
     */
    public function getCountParticipants()
    {
        return $this->assessment->getAllParticipants()->count();
    }

    /**
     * Returns the number of Participants who have no response to the assessment,
     * or their latest response is explicitly marked as 'incomplete'.
     *
     * @return int
     */
    public function getCountResponseRequired()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->isResponseRequired()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * How many Participants have Responses that require marking.
     *
     * @return int
     */
    public function getCountFeedbackRequired()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->isFeedbackRequired()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * How many participants has the teacher marked as not-explicitly-incomplete, and
     * the student has not subsequently submitted/re-submitted.
     *
     * @return int
     */
    public function getCountReviewComplete()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->isReviewComplete()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * How many participants have submitted ANY kind of response to this assessment?
     *
     * Includes teacher Submitting-On-Behalf-Of
     *
     * @return int
     */
    public function getCountHasSubmittedResponse()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->hasSubmittedResponse()) {
                $count++;
            }
        }
        return $count;
    }


    /**
     * Return the number of participants which have published feedback for this assessment
     *
     * @return int
     */
    public function getCountHasPublishedFeedback()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->hasPublishedFeedback()) {
                $count++;
            }
        }
        return $count;
    }


    /**
     * Return the number of participants which have a mark for this assessment
     *
     * This includes both marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @return int
     *
     */
    public function getCountHasMark()
    {
        $count = 0;
        foreach ($this->assessment->getAllParticipants() as $participant) {
            if ($participant->hasMark()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * What percentage of Participants still need to submit a response
     *
     * @return float|null
     */
    public function getPercentageResponseRequired()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountResponseRequired() / $countParticipants);
    }


    /**
     * What percentage of Participants have Responses that require marking.
     *
     * @return float|null
     */
    public function getPercentageFeedbackRequired()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountFeedbackRequired() / $countParticipants);
    }

    /**
     * What percentage of Participants have been review-completed ?
     *
     * @return float|null
     */
    public function getPercentageReviewComplete()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountReviewComplete() / $countParticipants);
    }

    /**
     * What percentage of Participants have submitted a response to this assessment ?
     *
     * @return float|null
     */
    public function getPercentageHasSubmittedResponse()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountHasSubmittedResponse() / $countParticipants);
    }

    /**
     * What percentage of Participants have received published feedback for this assessment ?
     *
     * @return float|null
     */
    public function getPercentageHasPublishedFeedback()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountHasPublishedFeedback() / $countParticipants);
    }

    /**
     * What percentage of Participants have a mark for this assessment ?
     *
     * @return float|null
     */
    public function getPercentageHasMark()
    {
        $countParticipants = $this->getCountParticipants();
        if(!$countParticipants){
            return null;
        }
        return 100 * ($this->getCountHasMark() / $countParticipants);
    }
}
