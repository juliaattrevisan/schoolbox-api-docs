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
     * These denote the kind of interaction one might encounter
     * when parsing the payloads. There are three types.
     */
    private const INTERACTION_RESPONSE = 'response';
    private const INTERACTION_FEEDBACK = 'feedback';
    private const INTERACTION_VIEW = 'view';


    /**
     * Controller constructor.
     */
    public function __construct(
        private \Schoolbox\Learning\Assessment\Views\Mapper $assessmentViewMapper
    )
    {
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

        /* A list of Responses and Feedbacks, as per the Submission History
         */
        $timeline = (new Full($participant))->getForTeacher();

        /* Convert this into a struct specifically for the ui
         */
        $stack = [];

        foreach ($timeline as $interaction) {
            if ($interaction instanceof AbstractFeedback) {
                $stack[] = [
                    'type' => self::INTERACTION_FEEDBACK,
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
                    'type' => self::INTERACTION_RESPONSE,
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

        /* A list of Views, which aren't really part of the Submission History
         * but we want to show them anyway. Shank them right in
         */
        $views = $this->assessmentViewMapper->findByColumnList([
            'assessment_id' => $assessment->getId(),
            'participant_id' => $student->getId(),
        ]);

        foreach ($views as $interaction) {
            $stack[] = [
                'type' => self::INTERACTION_VIEW,
                'viewer' => [
                    'id' => $interaction->getUser()->getId(),
                    'name' => $interaction->getUser()->getFullName(),
                ],
                'date' => [
                    'datetime' => $interaction->getLastVisit()->getDatabase(),
                    'short' => $interaction->getLastVisit()->getShort(),
                    'relative' => $interaction->getLastVisit()->getRelativeTime(),
                ]
            ];
        }

        /* Reverse chronologically sort that array stack again,
         * due to the shanking of Assessment Views
         */
        usort($stack, static function (array $a, array $b): int {
            return strtotime($b['date']['datetime']) <=> strtotime($a['date']['datetime']);
        });

        $this->getResponse()->content->set(
            $this->buildJsonOutput($stack)
        );
    }
}
