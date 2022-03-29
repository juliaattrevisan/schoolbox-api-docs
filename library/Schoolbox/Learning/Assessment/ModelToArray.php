<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Collection;

/**
 * Translator for translating an assessment model into an array, ready for JSON
 * encoding.
 *
 */
class ModelToArray
{
    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        return [
            'id'               => $assessment->getId(),
            'title'            => $assessment->getTitle(),
            'description'      => $assessment->getDescription(),
            'publishedAt'      => $assessment->getPublishAt(),
            'openAt'           => $assessment->getOpenAt(),
            'dueAt'            => $assessment->getDueAt(),
            'closeAt'          => $assessment->getCloseAt(),
            'defaultOpenSpan'  => $assessment->getDefaultOpenSpan(),
            'courseManaged'    => $assessment->getCourseManaged(),
            'sebEnabled'       => $assessment->getSebEnabled(),
            'weight'           => $assessment->getWeight(),
            'sequence'         => $assessment->getSequence(),
            'workType'         => $assessment->getWorkType(),
            'gradeGroup'       => $assessment->getGradeGroup(),
            'markScheme'       => $assessment->getMarkScheme(),
            'allParticipants'  => $this->formatParticipants($assessment->getAllParticipants()),
            'participantUsers' => $assessment->getParticipantUsers(),
            'creator'          => $assessment->getCreator(),
            'plans'            => $assessment->getPlans(),
            'hasDynamicPlan'   => $assessment->hasDynamicPlan(),
            'dynamicPlan'      => $assessment->getDynamicPlan(),
            'responseClass'    => $assessment->getResponseClass(),
            'feedbackClass'    => $assessment->getFeedbackClass(),
            'curriculumNodes'  => $assessment->getCurriculumNodes(),
            'createdAt'        => $assessment->getCreatedAt(),
            'updatedAt'        => $assessment->getUpdatedAt(),
            'deletedAt'        => $assessment->getDeletedAt(),
        ];
    }

    /**
     * get participants
     * @param Collection|Participant[] $participants
     */
    private function formatParticipants($participants)
    {
        $output = [];
        /** @var participant $participant */
        foreach ($participants as $participant) {
            $output[] = [
                'id' => $participant->getUser()->getId(),
                'name' => $participant->getUser()->getFullName(),
                'isExcluded' => $participant->isExcluded(),
                'publishDate' => $participant->getPublishDate(),
                'openDate' => $participant->getOpenDate(),
                'dueDate' => $participant->getDueDate(),
                'closeDate' => $participant->getCloseDate(),
                'description' => $participant->getDescription(),
            ];
        }
        return $output;
    }
}
