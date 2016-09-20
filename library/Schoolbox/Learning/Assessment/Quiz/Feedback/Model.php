<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;

/**
 * @method QuizAttempt getResponse()
 */
class Model extends AbstractFeedback
{
    /**
     * Calculate the normalised mark for this QuizFeedback
     *
     * @return float|null
     */
    public function getNormalisedMark()
    {
        $marksAvailable = $this->getResponse()->getMarksAvailable();

        /* No marks because no questions with mark values
         */
        if ($marksAvailable == 0) {
            return null;
        }

        $marksGiven = 0;
        foreach ($this->getResponse()->getAllResponses() as $response) {
            $marksGiven += $response['mark'];
        }

        return floatval(IMarkScheme::NORMALISED_MARK_MAX * $marksGiven / $marksAvailable);
    }



    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        $markScheme = $this->getAssessable()->getMarkScheme();
        return $markScheme->getHumanReadableMark($this->getNormalisedMark(), $this->getResponse());
    }

    /* Getters/Setters
     */

}