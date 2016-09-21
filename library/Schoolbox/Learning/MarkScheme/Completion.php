<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;
use Schoolbox\Learning\Assessment\IResponse;

class Completion extends AbstractMarkScheme
{
    const HUMAN_READABLE_INCOMPLETE        = 'Not Completed';
    const HUMAN_READABLE_STUDENT_COMPLETED = 'Student Assessed';
    const HUMAN_READABLE_TEACHER_COMPLETED = 'Teacher Assessed';

    /**
     * Returns a string representation of a normalised Mark
     *
     * @param mixed          $normalisedMark Ignored.
     * @param IResponse|null $response
     * @throws RangeException if normalised mark is outside valid range
     * @throws InvalidArgumentException if enum not matched
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if(null === $response){
            return self::HUMAN_READABLE_INCOMPLETE;
        } else if (count($response->getAllFeedback())){
            return self::HUMAN_READABLE_TEACHER_COMPLETED;
        } else if($response->getSubmittedBy()->getId() === $response->getSubmittedOnBehalfOf()->getId() ) {
            return self::HUMAN_READABLE_STUDENT_COMPLETED;
        } else {
            return self::HUMAN_READABLE_INCOMPLETE;
        }
    }

    /**
     * Returns null (this ensures Summators always ignore)
     *
     * @param string         $humanReadableMark eg 'Completed'
     * @param IResponse|null $response
     * @return null
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        return null;
    }
}