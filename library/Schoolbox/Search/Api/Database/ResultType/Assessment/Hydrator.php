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
use Schoolbox\Learning\Assessment\LessonPlan\Model;
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

                $keywordLower = strtolower($keyword); // Convert to lowercase for case-insensitive str_contains() searches below.
                $assessmentContentHtml = $this->getAssessmentHtmlContent($assessment);
                $assessmentContentTextOnly = (new ToText($assessmentContentHtml))->getText(); // Remove HTML tags.

                // Because the assessment content might contain HTML, this match might have come about only because the
                // keyword was found in HTML code that is invisible to the user (eg. 'style=').
                // Eliminate those kinds of matches.
                if ($this->assessmentMatchesKeywordOnlyOnHtmlContent($assessment, $keyword, $assessmentContentHtml)) {
                    if (!str_contains(strtolower($assessmentContentTextOnly), $keywordLower)) {
                        return null;
                    }
                }

                if (!($assessment instanceof Assessment\LessonPlan\Model)) {
                    // The content text with HTML tags removed is safe to use as the search result context.
                    $searchResultContext = $assessmentContentTextOnly;
                } else {
                    // For lesson plans only, we use a different method to get the search result context text.  This is
                    // necessary because lesson plan content is split into separate fields, and some fields are not
                    // visible to student users.
                    $fieldContentHtmlMatchingKeyword = (
                        $this->getLessonPlanFieldContentHtmlMatchingKeyword($assessment, $keyword, $viewer)
                    );

                    if ($fieldContentHtmlMatchingKeyword !== null) {
                        // The keyword was found in field content visible to the user.
                        // Set the search result context to the content of the field that contained the keyword.
                        $searchResultContextHtml = $fieldContentHtmlMatchingKeyword;
                    } else {
                        // The keyword was not found in any field content visible to the user.

                        // If the keyword is found in the lesson plan title, then the match is still good.
                        if (str_contains(strtolower($assessment->getTitle()), $keywordLower)) {
                            // Set the search result context to the content of the first field.
                            $searchResultContextHtml = (
                                $this->consistentlyFormatLessonPlanFieldContent($assessment->getFields()->first())
                            );
                        } else {
                            // This lesson plan is only here because it matched on content text that is invisible to the
                            // user.  Therefore, this match should be eliminated.
                            return null;
                        }
                    }

                    $searchResultContext = (new ToText($searchResultContextHtml))->getText(); // Remove HTML tags.
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
                    ($this->truncateAroundKeyword($keyword, $searchResultContext) ?? $this->truncate($searchResultContext)),
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
     * Return the $assessment's content as a string possibly containing HTML.
     *
     * An assessment's content is:
     *  * For LTI assessments:
     *    the LTI assessment text.
     *  * For lesson plans:
     *    the concatenation of the content of all of its fields (including the field titles).
     *  * For all other assessment types:
     *    $assessment->getDescription()
     *
     * @param IAssessable $assessment
     * @return string
     */
    private function getAssessmentHtmlContent(IAssessable $assessment): string
    {
        if ($assessment instanceof Assessment\Lti\Model) {
            return $assessment->getLtiAssessmentText();
        } elseif ($assessment instanceof Assessment\LessonPlan\Model) {
            return $this->getConcatenationOfLessonPlanFieldTitlesAndBodies($assessment);
        } else {
            return $assessment->getDescription();
        }
    }

    /**
     * @param Assessment\LessonPlan\Model $lessonPlan
     * @return string
     */
    private function getConcatenationOfLessonPlanFieldTitlesAndBodies(Assessment\LessonPlan\Model $lessonPlan): string
    {
        $strings = [];

        foreach ($lessonPlan->getFields() as $field) {
            $strings[] = $this->consistentlyFormatLessonPlanFieldContent($field);
        }

        return implode("\n\n", $strings);
    }

    /**
     * Return true if the keyword is contained in the $assessment's content (which might contain HTML), but not in any
     * of the non-HTML strings that could possibly cause matches.
     *
     * IMPORTANT: This function checks for case-insensitive matches.  This is to match the behaviour of an SQL 'LIKE' condition.
     *
     * The checks used here must match those in Assessment\QueryParamBuilder->buildBaseWhereParams()
     *
     * @param IAssessable $assessment
     * @param string      $keyword
     * @param string      $assessmentContentHtml
     * @return bool
     */
    private function assessmentMatchesKeywordOnlyOnHtmlContent(
        IAssessable $assessment,
        string $keyword,
        string $assessmentContentHtml
    ): bool {
        // SQL match conditions copied from Assessment\QueryParamBuilder->buildBaseWhereParams()
        // -------------------------------------------------------------------------------------
        // NOTES:
        //  * The Search Keyword Match Conditions below must match those used in QueryParamBuilder->buildBaseWhereParams().
        //  * The concatenation of all the lesson_plan_field titles and bodies is treated as HTML content.
        //    Also LTI assessment text is treated as HTML content.
        //    (see $this->getAssessmentHtmlContent())
        //
        // Search keyword match condition                                     Relevant field from model                        HtmlContent or Other?
        // -----------------------------------------------------------------------------------------------------------------------------------------
        // 'assessment.title LIKE :assessmentKeywordLike',                    // Assessment->getTitle()                              Other
        // 'assessment.description LIKE :assessmentKeywordLike',              // Assessment->getDescription()                        HtmlContent
        // 'task.link LIKE :assessmentKeywordLike',                           // Assessment\Task\Model->getUrl()                     Other
        // 'assess.title LIKE :assessmentKeywordLike',                        // Overrides Assessment->getTitle()                    Other
        // 'assess.description LIKE :assessmentKeywordLike',                  // Overrides Assessment->getDescription()              HtmlContent
        // 'lti_assessment.lti_assessment_url LIKE :assessmentKeywordLike',   // Assessment\Lti\Model->getLtiAssessmentUrl()         Other
        // 'lti_assessment.lti_assessment_title LIKE :assessmentKeywordLike', // Assessment\Lti\Model->getLtiAssessmentTitle()       Other
        // 'lti_assessment.lti_assessment_text LIKE :assessmentKeywordLike',  // Assessment\Lti\Model->getLtiAssessmentText()        HtmlContent
        // 'lesson_plan_field.title LIKE :assessmentKeywordLike',             // Assessment\LessonPlan\Model->getField()->getTitle() HtmlContent
        // 'lesson_plan_field.body LIKE :assessmentKeywordLike'               // Assessment\LessonPlan\Model->getField()->getBody()  HtmlContent

        $keywordLower = strtolower($keyword);

        if (str_contains(strtolower($assessment->getTitle()), $keywordLower)) {
            return false; // Found match on assessment.title or assess.title.
        }

        if ($assessment instanceof Assessment\Lti\Model) {
            if (
                str_contains(strtolower($assessment->getLtiAssessmentUrl()), $keywordLower) ||
                str_contains(strtolower($assessment->getLtiAssessmentTitle()), $keywordLower)
            ) {
                return false; // Found match on lti_assessment.lti_assessment_url or lti_assessment.lti_assessment_title.
            }
        } elseif ($assessment instanceof Assessment\Task\Model) {
            if (str_contains(strtolower($assessment->getUrl()), $keywordLower)) {
                return false; // Found match on task.link.
            }
        }

        // No other match found, so return true if the keyword matches on the HTML content.
        return str_contains(strtolower($assessmentContentHtml), $keywordLower);
    }

    /**
     * @param Model  $lessonPlan
     * @param string $keyword
     * @param User   $viewer
     * @return string | null
     */
    private function getLessonPlanFieldContentHtmlMatchingKeyword(
        Assessment\LessonPlan\Model $lessonPlan,
        string $keyword,
        User $viewer
    ): ?string {
        // Lesson plan fields have a visibility enum that is either 'staff' or 'student' (lesson_plan_field.visibility).
        // For the purposes of this permission check, superusers are treated as staff, and anyone who is not
        // staff or superuser is treated as a student.
        $treatViewerAsStudent = !($viewer->isSuperuser() || $viewer->getRole()->isStaff());

        $fieldContentHtmlToReturn = null;
        $keywordLower = strtolower($keyword);

        // Find visible content that contains the search keyword.
        foreach ($lessonPlan->getFields() as $field) {
            if ($treatViewerAsStudent && $field->getVisibility() !== 'student') {
                // No visible content here.
                continue;
            }

            if (
                // Use str_contains on lowercase strings to simulate case-insensitive match used by SQL LIKE condition.{
                str_contains(strtolower($field->getTitle()), $keywordLower) ||
                str_contains(strtolower($field->getBody()), $keywordLower)
            ) {
                // Found the keyword in text that is visible to the user, so this field's text is safe to
                // use as the search result context text.
                $fieldContentHtmlToReturn = $this->consistentlyFormatLessonPlanFieldContent($field);
                break;
            }
        }

        return $fieldContentHtmlToReturn;
    }

    /**
     * @param Assessment\DataModel\LessonPlan\Field\Model $field
     * @return string
     */
    private function consistentlyFormatLessonPlanFieldContent(Assessment\DataModel\LessonPlan\Field\Model $field): string
    {
        return $field->getTitle() . " -\n\n" . $field->getBody();
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param IAssessable $assessment
     * @param User        $viewer
     * @return array array<array{"title": string, "icon": string, "url": string, "action"?: string}>
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

        if (!($assessment instanceof Assessment\LessonPlan\Model) && $assessment->isImplementation()) {
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
