<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Views\Mapper;
use Schoolbox\User;

/**
 * A Full timeline with user Views augmented into the data set
 * allowing you to get a complete picture of academic interactions
 * along with observational interactions
 *
 * Currently used only for an async endpoint which is why it gives
 * you a flattened simplified array
 */
class FullWithViews
{
    /**
     * These denote the kind of interaction one might encounter
     * when parsing the payloads. There are three types.
     */
    private const INTERACTION_RESPONSE = 'response';
    private const INTERACTION_FEEDBACK = 'feedback';
    private const INTERACTION_VIEW = 'view';


    /**
     * @param Participant $participant
     * @param Mapper      $assessmentViewMapper
     */
    public function __construct(
        private Participant $participant,
        private \Schoolbox\Learning\Assessment\Views\Mapper $assessmentViewMapper
    ) {
    }


    /**
     * @param IAssessable $assessment
     * @param User        $student
     * @return array
     */
    public function getForTeacher(IAssessable $assessment, User $student): array
    {
        $stack = [];

        /* A list of Responses and Floating Feedbacks, as per the Submission History
         */
        $timeline = (new Full($this->participant))->getForTeacher();

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

        /* Dig out all the Associated Feedbacks
         */
        foreach ($this->participant->getAllResponses() as $response) {
            foreach ($response->getAllFeedback() as $interaction) {
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
         * due to the shanking of Assessment Views and unfolding
         * of Associated Feedbacks
         */
        usort($stack, static function (array $a, array $b): int {
            return strtotime($b['date']['datetime']) <=> strtotime($a['date']['datetime']);
        });

        return $stack;
    }
}
