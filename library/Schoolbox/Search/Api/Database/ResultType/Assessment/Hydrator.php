<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlanModel;
use Schoolbox\Learning\Assessment\Lti\Model as LtiModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizModel;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
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

//                if ($assessment instanceof DueWorkModel) {
//                    // ...
//                } elseif ($assessment instanceof LessonPlanModel) {
//                    // ...
//                } elseif ($assessment instanceof ProjectModel) {
//                    // ...
//                } elseif ($assessment instanceof LtiModel) {
//                    // ...
//                } elseif ($assessment instanceof TaskModel) {
//                    // ...
//                } elseif ($assessment instanceof QuizModel) {
//                    // ...
                if ($assessment instanceof AssessmentModel) {
                    $quiz = &$assessment;

                    $folder = $assessment->getContextFolder();

                    if ($folder !== null) {
                        // Check if user has access to quiz's folder
                        $quizAccess = $this->access->getFolderAccess($viewer, $quiz->getContextFolder()->getId());
                        if ($quizAccess < UserAccess::ACCESS_READ) {
                            return null;
                        }
                    }

                    $context = [];

                    if ($folder !== null) {
                        $context = $folder->getId();
                    }

                    return new FullResult(
                        $match,
                        $match->id,
                        $quiz->getTitle(),
                        $this->buildBaseUrl()
                             ->withPath("/learning/quiz/{$quiz->getId()}")
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $quiz->getUpdatedAt(),
                        $folder?->getId(),
                        null,
                        null,
                        'quiz',
                        $quiz->getDescription(),
                        null,
                        $context, // Context: quiz's folder
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
