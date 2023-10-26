<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Feedback;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;

/**
 * @method QuizAttempt|null getResponse()
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

        return $this->getResponse()->getNormalisedMark();
    }


    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        return $this->getResponse()->getHumanReadableMark();
    }
}
