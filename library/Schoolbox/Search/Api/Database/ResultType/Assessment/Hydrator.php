<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress\Authorizer\Authorizer;
use Alaress\Html\ToText;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;
use Schoolbox\UserMapper;

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
     * @param UserMapper $userMapper Used to hydrate assessment creators.
     * @param Access $access Used to determine if the current user may view this page.
     * @param Authorizer $authorizer Used to determine if the current user may view system pages.
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private UserMapper $userMapper,
        private Access $access,
        private Generator $routeGenerator,
        private Authorizer $authorizer,
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
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all assessments that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching assessments.
        $assessments = $this->assessmentMapperProxy->findAllByIds($ids);
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

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

        if (count($assessments) === 0) {
            return [];
        }

        // Note creator is not hydrated in $assessments, so get assessment creators separately.
        $creatorIds = [];
        foreach ($assessments as $assessment) {
            $assessmentCreator = $assessment->getCreator();

            // NOTE: $assessmentCreator might still be null, because of bad data, or because a created_by field does not
            //       yet exist on the assessment type.  E.g. as of 23.01 lti_assessment has no created_by/owner_id/user_id
            //       or similar field.
            $creatorId = $assessmentCreator?->getId();
            if ($creatorId !== null) {
                $creatorIds[] = $creatorId;
            }
        }
        $assessmentCreatorById = $this->userMapper->findByColumn('id', $creatorIds)->indexBy('id');

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($assessments, $assessmentCreatorById, $keyword, $viewer) {
                $assessment = $assessments->getBy('id', $match->id);

                if (!($assessment instanceof IAssessable)) {
                    return null;
                }

                $assessmentDesc = $assessment->getDescription();

                // Because the description contains HTML, this match might have come about only because the
                // keyword was found in HTML code that is invisible to the user (eg. 'style=').
                // Eliminate those kinds of matches.
                if ($this->assessmentMatchesKeywordOnlyOnDescription($assessment, $keyword)) {
                    $assessmentDescTextOnly = (new ToText($assessmentDesc))->getText();
                    if (!str_contains($assessmentDescTextOnly, $keyword)) {
                        return null;
                    }
                }

                $assessmentCreator = $assessment->getCreator();

                // Assessment->creator is not hydrated, so get from $assessmentCreatorById instead.
                // NOTE: $assessmentCreator might be null here due to note above.
                if ($assessmentCreator !== null) {
                    $assessmentCreator = $assessmentCreatorById[$assessmentCreator->getId()] ?? null;
                }

                // Note $match->type will be something like 'assessment.dueWork'.
                $matchTypeTokens = explode('.', $match->type);
                $assessmentType = $matchTypeTokens[1];
                $assessmentDueAt = $assessment->getDueAt();
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
                    (
                        $assessment->isImplementation()
                        ? 'Due ' . ($assessmentDueAt === null ? '[no date set]' : $assessmentDueAt->getLocal())
                        : $assessment->getWorkType()?->getName()
                    ),
                    null,
                    AssessmentType::getIconClassSuffixForType($assessmentType),
                    $this->truncateAroundKeyword($keyword, $assessmentDesc) ?? $this->truncate($assessmentDesc),
                    $assessmentCreator,
                    [$folder],
                    $this->getSearchResultLinks($assessment, $viewer),
                );
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * Return true if the keyword is contained in the $assessment's description, but not in any of the other
     * strings that could possibly cause matches.
     *
     * The checks used here must match those in Assessment\QueryParamBuilder->buildBaseWhereParams()
     *
     * @param IAssessable $assessment
     * @param string      $keyword
     * @return bool
     */
    private function assessmentMatchesKeywordOnlyOnDescription(IAssessable $assessment, string $keyword): bool
    {
        // SQL match conditions copied from Assessment\QueryParamBuilder->buildBaseWhereParams()
        // -------------------------------------------------------------------------------------
        // 'assessment.title LIKE :assessmentKeywordLike',                    // Assessment->getTitle()
        // 'assessment.description LIKE :assessmentKeywordLike',              // Assessment->getDescription()
        // 'task.link LIKE :assessmentKeywordLike',                           // Assessment\Task\Model
        // 'assess.title LIKE :assessmentKeywordLike',                        // Overrides Assessment->getTitle()
        // 'assess.description LIKE :assessmentKeywordLike',                  // Overrides Assessment->getDescription()
        // 'lti_assessment.lti_assessment_url LIKE :assessmentKeywordLike',   // Assessment\Lti\Model
        // 'lti_assessment.lti_assessment_title LIKE :assessmentKeywordLike', // Assessment\Lti\Model
        // 'lti_assessment.lti_assessment_text LIKE :assessmentKeywordLike',  // Assessment\Lti\Model

        if (str_contains($assessment->getTitle(), $keyword)) {
            return false; // Found match on assessment.title or assess.title.
        }

        if ($assessment instanceof Assessment\Lti\Model) {
            if (
                str_contains($assessment->getLtiAssessmentUrl(), $keyword) ||
                str_contains($assessment->getLtiAssessmentTitle(), $keyword) ||
                str_contains($assessment->getLtiAssessmentText(), $keyword)
            ) {
                return false; // Found match on lti_assessment url, title, or text.
            }
        }

        if ($assessment instanceof Assessment\Task\Model) {
            if (str_contains($assessment->getUrl(), $keyword)) {
                return false; // Found match on task.link.
            }
        }

        // No other match found, so return true if match on description.
        return str_contains($assessment->getDescription(), $keyword);
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param IAssessable $assessment
     * @param User        $viewer
     * @return array array{array{"title": string, "icon": string, "url": string, "action"?: string}}
     * @throws RouteNotFound
     */
    private function getSearchResultLinks(IAssessable $assessment, User $viewer): array
    {
        $links = [];

        $route = 'learningAssessment.getUpdateAssessmentForm';
        $params = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            $links[] = [
                // All assessment types use the same URL for their 'Modify' page.
                'url' => $this->routeGenerator->generate($route, $params),
                'title' => 'Modify',
                'icon' => 'icon-modify',
            ];
        }

        if (!($assessment instanceof Assessment\LessonPlan\Model)) {
            $route = 'learningAssessment.getStatus';
            $params = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    // All assessment types use the same URL for their 'View Status' page,
                    // except lesson plans.  Lesson plans have no 'View Status' page.
                    'url' => $this->routeGenerator->generate($route, $params),
                    'title' => 'View Status',
                    'icon' => 'icon-progress',
                ];
            }
        }

        return $links;
    }
}
