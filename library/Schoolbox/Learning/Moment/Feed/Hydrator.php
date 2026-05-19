<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Moment\Mapper as EvidenceMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\User;

/**
 * Hydrator for the list of IDs retrieved by `\Schoolbox\Learning\Moment\Feed\Cursor`.
 */
class Hydrator
{
    /**
     * Constructor
     *
     * @param EvidenceMapper $evidenceMapper
     * @param BadgeAwardMapper $badgeAwardMapper
     * @param DueWorkFeedbackMapperProxy $returnDataSource
     * @param QuizFeedbackMapperProxy $attemptDataSource
     * @param MapperProxy $assessmentDataSource
     * @param Visibility $visibility
     * @param User $viewer
     */
    public function __construct(
        private EvidenceMapper $evidenceMapper,
        private BadgeAwardMapper $badgeAwardMapper,
        private DueWorkFeedbackMapperProxy $returnDataSource,
        private QuizFeedbackMapperProxy $attemptDataSource,
        private MapperProxy $assessmentDataSource,
        private Visibility $visibility,
        private User $viewer,
    ) {
    }

    /**
     * Hydrates the results from `\Schoolbox\Learning\Moment\Feed\Cursor::retrieveResults`
     * into a set of actual objects.
     *
     * @param Collection $results
     * @return Collection Each item  will contain:
     *                    * discriminator: identifier of object's type
     *                    * object:        the actual object
     */
    public function hydrateResults(Collection $results): Collection
    {
        $evidencePb  = $this->evidenceMapper->getPredicateBuilder();
        $hasEvidence = false;

        // Again, badge award not having IDs could be a problem
        // Just assume a badge can't be awarded twice at the same time
        $badgeAwardPb   = $this->badgeAwardMapper->getPredicateBuilder();
        $hasBadgeAwards = false;

        $dueWorkFeedbackIds = [];
        $quizFeedbackIds = [];

        // Build predicate builders
        foreach ($results as $result) {
            switch ($result['discriminator'] ?? null) {
                case Cursor::DISCRIMINATOR_EVIDENCE:
                case Cursor::DISCRIMINATOR_EVIDENCE_OLD:
                    $hasEvidence = true;
                    $evidencePb->all(
                        $this->evidenceMapper->getPredicateBuilder()
                                             ->equals('id', $result['id'])
                                             ->isNull('deleted_at')
                    );
                    break;

                case Cursor::DISCRIMINATOR_BADGE_AWARD:
                    $hasBadgeAwards = true;
                    $badgeAwardPb->all(
                        $this->badgeAwardMapper->getPredicateBuilder()
                                               ->equals('id', $result['id'])
                                               ->isNull('deleted_at')
                    );
                    break;

                case Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK:
                    $dueWorkFeedbackIds[] = $result['id'];
                    break;

                case Cursor::DISCRIMINATOR_QUIZ_FEEDBACK:
                    $quizFeedbackIds[] = $result['id'];
                    break;

                default:
                    // nothing happens
                    break;
            }
        }

        // Query
        $evidenceResults = $hasEvidence
            ? $this->evidenceMapper->findByPredicates(
                $this->evidenceMapper->getPredicateBuilder()->any($evidencePb)
            )
            : $this->evidenceMapper->createCollection();
        $evidenceResults = $evidenceResults->indexBy('id');

        $badgeAwardResults = $hasBadgeAwards
            ? $this->badgeAwardMapper->findByPredicates(
                $this->badgeAwardMapper->getPredicateBuilder()->any($badgeAwardPb)
            )
            : $this->badgeAwardMapper->createCollection();
        $badgeAwardResults = $badgeAwardResults->indexBy('id');

        $dueWorkFeedbackResults = !empty($dueWorkFeedbackIds)
            ? $this->returnDataSource->findByColumnList(['id' => $dueWorkFeedbackIds])->indexBy('id')
            : new GenericCollection(DueWorkFeedback::class);

        $quizFeedbackResults = !empty($quizFeedbackIds)
            ? $this->attemptDataSource->findByColumnList(['attempt_id' => $quizFeedbackIds])->indexBy('id')
            : new GenericCollection(QuizFeedback::class);

        $assessmentIds = $dueWorkFeedbackResults->map(
            /**
             * Extracts assessment IDs referenced by due work feedback rows.
             */
            static function (DueWorkFeedback $feedback) {
                return $feedback->getAssessable()->getId();
            }
        );

        /* Add the quiz ones too
         */
        $assessmentIds = $assessmentIds->merge($quizFeedbackResults->map(static function (QuizFeedback $feedback) {
            return $feedback->getAssessable()->getId();
        }));

        $assessments = $this->assessmentDataSource->findAllByIds($assessmentIds->getAll())->indexBy('id');
        $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($assessments);

        // Insert matching results into the collection
        $return = new Collection();

        foreach ($results as $i => $result) {
            switch ($result['discriminator'] ?? null) {
                case Cursor::DISCRIMINATOR_EVIDENCE:
                case Cursor::DISCRIMINATOR_EVIDENCE_OLD:
                    // Find the evidence
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $evidenceResults[$result['id']],
                    ];
                    break;

                case Cursor::DISCRIMINATOR_BADGE_AWARD:
                    // find the badge award
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $badgeAwardResults[(int)$result['id']],
                    ];
                    break;

                case Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK:
                    /** @var DueWorkFeedback|null $dueWorkFeedback */
                    $dueWorkFeedback = $dueWorkFeedbackResults->offsetGet((int)$result['id']);
                    /** @var IAssessable|null $assessment */
                    $assessment = $assessments->offsetGet($dueWorkFeedback?->getAssessable()->getId());
                    if (
                        !$assessment
                        || !$this->visibility->isVisibleToUser($assessment, $this->viewer, true)
                        || !$assessment->isFeedbackVisibleToUser($this->viewer)
                    ) {
                        break;
                    }
                    // hydrate assessment
                    $dueWorkFeedback->setAssessable($assessment);
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $dueWorkFeedback,
                    ];
                    break;

                case Cursor::DISCRIMINATOR_QUIZ_FEEDBACK:
                    /** @var QuizFeedback|null $quizFeedback */
                    $quizFeedback = $quizFeedbackResults->offsetGet((int)$result['id']);
                    /** @var IAssessable|null $assessment */
                    $assessment = $assessments->offsetGet($quizFeedback?->getAssessable()->getId());
                    if (
                        !$assessment
                        || !$this->visibility->isVisibleToUser($assessment, $this->viewer, true)
                        || !$assessment->isFeedbackVisibleToUser($this->viewer)
                    ) {
                        break;
                    }
                    // hydrate assessment
                    $quizFeedback->setAssessable($assessment);
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $quizFeedback,
                    ];
                    break;

                default:
                    // nothing happens
                    break;
            }
        }

        return $return;
    }
}
