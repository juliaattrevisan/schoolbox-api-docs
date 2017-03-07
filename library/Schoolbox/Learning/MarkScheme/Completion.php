<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;

class Completion extends AbstractMarkScheme
{
    const TITLE = 'Completion';

    const HUMAN_READABLE_INCOMPLETE  = 'Incomplete';
    const HUMAN_READABLE_COMPLETE    = 'Complete';

    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = false;

    /**
     * Returns a string representation of a normalised Mark
     *
     * @param mixed                       $normalisedMark  - ignored, even if invalid
     * @param IResponse|null $response
     * @return string|null
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if (null === $response || ($response instanceof TaskResponse && !$response->getMarkedComplete())){
            return self::HUMAN_READABLE_INCOMPLETE;
        } else {
            return self::HUMAN_READABLE_COMPLETE;
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
        if (null === $response || ($response instanceof TaskResponse && !$response->getMarkedComplete())){
            return self::NORMALISED_MARK_MIN;
        } else {
            return self::NORMALISED_MARK_MAX;
        }
    }


    /**
     * @inheritdoc
     */
    public function getPublishedHumanReadableMark(Participant $participant)
    {
        return $this->getHumanReadableMark(null, $participant->findMostRecentSubmittedResponse());
    }

    /**
     * @inheritdoc
     */
    public function getMostRecentHumanReadableMark(Participant $participant)
    {
        return $this->getHumanReadableMark(null, $participant->findMostRecentSubmittedResponse());
    }
}
