<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for assessment results.
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
     * @param AssessmentMapperProxy $assessmentMapperProxy Used to fetch assessments for hydration.
     * @param Access $access Used to determine if the current user may view this page.
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
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
        // Find all assessments that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching assessments.
        $assessments = $this->assessmentMapperProxy->findAllByIds($ids);

        // Permissions (from AC of SA-16014):
        //    Students
        //     - Should only see assessments in their own classes/groups
        //     - Should only see assessments that they have not been excluded from
        //     - Should only see assessments that have reached there open date
        //    Staff
        //     - Should see assessments in their own classes
        //     - Should see assessments in units that they have permission to access
        // The Visibility class is relied on to check the above conditions.
        $mutator = new AssessmentListMutator($viewer, new Visibility($this->access), Alaress_Date_Format::now(), true);
        $assessments = $assessments
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter']);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($assessments) {
                $assessment = $assessments->getBy('id', $match->id);

                if ($assessment instanceof IAssessable) {
                    // Note $match->type will be something like 'assessment.dueWork'.
                    $matchTypeTokens = explode('.', $match->type);
                    $assessmentType = $matchTypeTokens[1];
                    $folder = $assessment->getBaseContext()->getFolder();

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
                        $assessment->getCreator(),
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
