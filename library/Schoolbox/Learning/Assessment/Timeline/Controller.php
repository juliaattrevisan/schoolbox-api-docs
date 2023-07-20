<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress\Web\Controller\Base;
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
    public function __construct(
        private \Schoolbox\Learning\Assessment\Views\Mapper $assessmentViewMapper
    ) {
    }


    /**
     * Dump the full timeline of $assessment for $user
     *
     * There's a shanking of Assessment Views which isn't part of the Timeline
     * usually; a future improvement would be to incorporate that optionally
     * into the Full Timeline
     *
     * @param User        $student
     * @param IAssessable $assessment
     */
    public function getFullTimeline(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);

        /* Convert this into a struct specifically for the ui
         */
        $stack = [];

        /* null participants return empty set
         */
        if (null !== $participant) {
            $stack = (new FullWithViews($participant, $this->assessmentViewMapper))->getForTeacher($assessment, $student);
        }

        $this->response = $this->responseAsJson($stack);
    }
}
