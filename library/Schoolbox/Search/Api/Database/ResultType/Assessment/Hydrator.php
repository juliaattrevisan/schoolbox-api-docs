<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for link results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'assessment';

    /**
     * Constructor
     *
     * @param AssessmentMapper $assessmentMapper Used to fetch assessments for hydration.
     * @param Access           $access           Used to determine if the current user may view this page.
     */
    public function __construct(
        private AssessmentMapper $assessmentMapper,
        private Access $access,
    ) {
    }

    /**
     * @inheritdoc
     */
    protected function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all links that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching links.
        $assessments = $this->assessmentMapper->findByColumn('id', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($assessments, $viewer) {
                $assessment = $assessments->getBy('id', $match->id);

                if ($assessment instanceof AssessmentModel) {
                    // Note $match->type will be something like 'assessment.dueWork'.
                    $matchTypeTokens = explode('.', $match->type);
                    $assessmentType = $matchTypeTokens[1];

                    $folder = (
                        ($assessment->getContextUnitImplementation() !== null)
                        ? $assessment->getContextUnitImplementation()->getFolder()
                        : $assessment->getContextUnitSpecification()?->getFolder()
                    );

                    if ($folder === null) {
                        throw new \LogicException('No folder found for assessment ' . $assessment->getId());
                    }

                    // Check if user has access to assessment's context folder
                    $assessmentAccess = $this->access->getFolderAccess($viewer, $folder->getId());
                    if ($assessmentAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }

                    // Checklist
                    //  * dueWork - results appear, nested in folders - DONE.
                    //  * lessonPlan - results appear, nested in folders - DONE.
                    //  * lti - TODO: get results to display
                    //  * project - results appear, nested in folders - DONE.
                    //  * quiz - results appear, nested in folders - DONE.
                    //  * task - results appear, not nested in folders.

                    return new FullResult(
                        $match,
                        $match->id,
                        $assessment->getTitle(),
                        $this->buildBaseUrl()
                             ->withPath("/learning/assessments/{$assessment->getId()}")
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $assessment->getUpdatedAt(),
                        $folder,
                        null,
                        null,
                        AssessmentType::getIconClassSuffixForType($assessmentType),
                        strip_tags($assessment->getDescription()),
                        match ($assessmentType) { // Get creatorId depending on type.
                            AssessmentType::TYPE_DUE_WORK => $assessment->getDueWork()->getOwner(),
                            AssessmentType::TYPE_QUIZ => $assessment->getQuiz()->getUser(),
                            AssessmentType::TYPE_TASK => $assessment->getTask()->getOwner(),
                            AssessmentType::TYPE_PROJECT => $assessment->getProject()->getOwner(),
                            AssessmentType::TYPE_LTI_ASSESSMENT => null,
                            AssessmentType::TYPE_LESSON_PLAN => $assessment->getLessonPlan()->getCreatedBy()
                        },
                        [$folder],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
