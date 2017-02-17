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
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;

class Completion extends AbstractMarkScheme
{
    const TITLE = 'Completion';

    const HUMAN_READABLE_INCOMPLETE        = 'Not Completed';
    const HUMAN_READABLE_STUDENT_COMPLETED = 'Student-Assessed';
    const HUMAN_READABLE_TEACHER_COMPLETED = 'Teacher-Assessed';

    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = false;

    /**
     * Returns a string representation of a normalised Mark
     *
     * @param mixed          $normalisedMark Ignored.
     * @param IResponse|TaskCompletion|null $response
     * @throws RangeException if normalised mark is outside valid range
     * @throws InvalidArgumentException if enum not matched
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if (null === $response || $response->isMarkedAsIncomplete()) {
            return self::HUMAN_READABLE_INCOMPLETE;
        } elseif ($response->isMarkedAsSelfAssessed()) {
            return self::HUMAN_READABLE_STUDENT_COMPLETED;
        } elseif ($response->isMarkedAsTeacherAssessed()) {
            return self::HUMAN_READABLE_TEACHER_COMPLETED;
        } else {
            throw new \DomainException("Unable to determine Task's human-readable mark");
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
