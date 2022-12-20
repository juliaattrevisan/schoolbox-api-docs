<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Access the various Timelines asynchronously by visiting this area
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Dump the full timeline of $assessment for $user
     *
     * @param User        $student
     * @param IAssessable $assessment
     */
    public function getFullTimeline(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);

        $timeline = (new Full($participant))->getForTeacher();

        /* Convert this into a struct specifically for the ui
         */
        $stack = [];

        foreach ($timeline as $interaction) {
            if ($interaction instanceof AbstractFeedback) {
                $stack[] = [
                    'type' => 'feedback',
                    'instructor' => [
                        'id' => $interaction->getReviewer()->getId(),
                        'name' => $interaction->getReviewer()->getFullname(),
                    ],
                    'learner' => [
                        'id' => $interaction->getReviewee()->getId(),
                        'name' => $interaction->getReviewee()->getFullname()
                    ],
                    'date' => [
                        'datetime' => $interaction->getPublishedAt()->getDatabase(),
                        'short' => $interaction->getPublishedAt()->getShort(),
                        'relative' => $interaction->getPublishedAt()->getRelativeTime(),
                    ]
                ];
            } elseif ($interaction instanceof AbstractResponse) {
                $stack[] = [
                    'type' => 'response',
                    'learner' => [
                        'id' => $interaction->getSubmittedOnBehalfOf()->getId(),
                        'name' => $interaction->getSubmittedOnBehalfOf()->getFullname()
                    ],
                    'date' => [
                        'datetime' => $interaction->getSubmittedAt()->getDatabase(),
                        'short' => $interaction->getSubmittedAt()->getShort(),
                        'relative' => $interaction->getSubmittedAt()->getRelativeTime(),
                    ]
                ];
            }
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput($stack)
        );
    }
}
