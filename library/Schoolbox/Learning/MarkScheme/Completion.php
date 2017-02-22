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
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;

class Completion extends AbstractMarkScheme
{
    const TITLE = 'Completion';

    const HUMAN_READABLE_INCOMPLETE = 'Incomplete';
    const HUMAN_READABLE_MARKED_COMPLETE_BY_STUDENT  = 'Complete';
    const HUMAN_READABLE_MARKED_COMPLETE_BY_TEACHER  = 'Complete';

    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = false;

    /**
     * Returns a string representation of a normalised Mark
     *
     * @param mixed                       $normalisedMark Ignored.
     * @param IResponse|TaskResponse|null $response
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if (null === $response || !$response->getMarkedComplete()) {
            return self::HUMAN_READABLE_INCOMPLETE;
        } elseif($response->isSelfAssessed()) {
            return self::HUMAN_READABLE_MARKED_COMPLETE_BY_STUDENT;
        } else {
            return self::HUMAN_READABLE_MARKED_COMPLETE_BY_TEACHER;
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
