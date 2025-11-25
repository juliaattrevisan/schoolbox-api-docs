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
use Schoolbox\Learning\Assessment\DialogAgent;
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
            $date = [
                'datetime' => $interaction->getUpdatedAt()->getDatabase(),
                'short' => $interaction->getUpdatedAt()->getShort(),
                'relative' => $interaction->getUpdatedAt()->getRelativeTime(),
            ];
            if ($interaction instanceof AbstractFeedback) {
                if ($interaction->isPublished()) {
                    $date = [
                        'datetime' => $interaction->getPublishedAt()->getDatabase(),
                        'short' => $interaction->getPublishedAt()->getShort(),
                        'relative' => $interaction->getPublishedAt()->getRelativeTime(),
                    ];
                }
                $stack[] = [
                    'type' => self::INTERACTION_FEEDBACK,
                    'effector' => [
                        'id' => $interaction->getReviewer()->getId(),
                        'name' => $interaction->getReviewer()->getFullname(),
                    ],
                    'published' => $interaction->isPublished(),
                    'date' => $date
                ];
            } elseif ($interaction instanceof AbstractResponse) {
                if ($interaction->isSubmitted()) {
                    $date = [
                        'datetime' => $interaction->getSubmittedAt()->getDatabase(),
                        'short' => $interaction->getSubmittedAt()->getShort(),
                        'relative' => $interaction->getSubmittedAt()->getRelativeTime(),
                    ];
                }

                /* I am not a fan of this being here at all, but this concept is entirely unique to Dialog Agents... */
                $assistanceRequested = null;
                if ($interaction instanceof DialogAgent\Response\Model) {
                    $assistanceRequested = $interaction->getAssistanceRequestedAt() !== null;
                }

                $stack[] = [
                    'type' => self::INTERACTION_RESPONSE,
                    'effector' => [
                        'id' => $interaction->getSubmittedOnBehalfOf()->getId(),
                        'name' => $interaction->getSubmittedOnBehalfOf()->getFullname()
                    ],
                    'submitted' => $interaction->isSubmitted(),
                    'submittedOnTime' => $interaction->isSubmittedOnTime(),
                    'assistanceRequested' => $assistanceRequested,
                    'date' => $date
                ];
            }
        }

        /* Dig out all the Associated Feedbacks
         */
        foreach ($this->participant->getAllResponses() as $response) {
            foreach ($response->getAllFeedback() as $interaction) {
                $date = [
                    'datetime' => $interaction->getUpdatedAt()->getDatabase(),
                    'short' => $interaction->getUpdatedAt()->getShort(),
                    'relative' => $interaction->getUpdatedAt()->getRelativeTime(),
                ];
                if ($interaction->isPublished()) {
                    $date = [
                        'datetime' => $interaction->getPublishedAt()->getDatabase(),
                        'short' => $interaction->getPublishedAt()->getShort(),
                        'relative' => $interaction->getPublishedAt()->getRelativeTime(),
                    ];
                }
                $stack[] = [
                    'type' => self::INTERACTION_FEEDBACK,
                    'effector' => [
                        'id' => $interaction->getReviewer()->getId(),
                        'name' => $interaction->getReviewer()->getFullname(),
                    ],
                    'published' => $interaction->isPublished(),
                    'date' => $date,
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
                'effector' => [
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
