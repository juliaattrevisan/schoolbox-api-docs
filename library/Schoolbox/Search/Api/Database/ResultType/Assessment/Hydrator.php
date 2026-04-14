<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress\Authorizer\Authorizer;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Learning\Assessment\Redirect;
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

    // TODO: this should probably be an array_map of AssessmentType::ALL_TYPES instead,
    // but leaving as-is for now as you can't array_map in a class const
    public const SUB_TYPES = [
        // Filter types and subtypes are defined in ResultTypes.ts.  The subtypes below must match that file.
        'assessment.dueWork',
        'assessment.lessonPlan',
        'assessment.project',
        'assessment.lti',
        'assessment.task',
        'assessment.quiz',
        'assessment.dialogAgent',
        'assessment.reporting',
    ];

    /**
     * Constructor
     *
     * @param RosettaStone $rosettaStone For translating terminology.
     * @param AssessmentMapperProxy $assessmentMapperProxy Used to fetch assessments for hydration.
     * @param UserMapper $userMapper Used to hydrate assessment creators.
     * @param Access $access Used to determine if the current user may view this page.
     * @param Authorizer $authorizer Used to determine if the current user may view system pages.
     * @param ServerRequestInterface $request Used to get the request URL.
     */
    public function __construct(
        private RosettaStone $rosettaStone,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private UserMapper $userMapper,
        private Access $access,
        private Generator $routeGenerator,
        private Authorizer $authorizer,
        private ServerRequestInterface $request
    ) {
    }

    /**
     * @inheritdoc
     * @throws RouteNotFound
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
        //     - Should only see assessments that have been published
        //    Staff
        //     - Should see assessments in their own classes
        //     - Should see assessments in units that they have permission to access
        // The Visibility class is relied on to check (most of the) above conditions.  However, it was found that this
        // check is insufficient, so more permission checks have been added below.
        $mutator = new AssessmentListMutator(
            $viewer,
            new Visibility($this->access),
            new ExternalUser(),
            Alaress_Date_Format::now(),
            true
        );
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

                // This permissions check was added because the Visibility check above was found to be insufficient.
                // From permissions summary above:
                //    Students
                //     - Should only see assessments that have been published
                if ($viewer->getRole()->isStudent() && $assessment->getPublishAt()?->isFuture()) {
                    return null;
                }

                $route = 'learningAssessment.getSummary';
                $params = [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'studentId' => $viewer->getId(),
                    'student' => $viewer
                ];

                // This permissions check was added because the Visibility check above was found to be insufficient.
                // Specifically, students were able to view lesson plans that are not in the class - only in the course.
                if (!$this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                    return null;
                }

                $assessmentContentHtml = $this->getAssessmentHtmlContent($assessment);
                $assessmentContentTextOnly = $this->htmlToText($assessmentContentHtml);

                // This formerly eliminated matches on invisible HTML tags
                // (eg. 'style='): now that the search index is prebuilt without
                // these tags, this is no longer necessary.

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
                        $searchResultContextHtml = $this->consistentlyFormatLessonPlanFieldContent(
                            $assessment->getFields()->first(),
                        );
                    }

                    $searchResultContext = $this->htmlToText($searchResultContextHtml);
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
                    $this->routeGenerator->generate($route, $params),
                    $match->type,
                    $this->getTypeTitleFromType($match->type, $this->rosettaStone),
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
            return $assessment->getDescription() ?? ''; // Note assessment.description is nullable.
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

        // Find visible content that contains the search keyword.
        foreach ($lessonPlan->getFields() as $field) {
            if ($treatViewerAsStudent && $field->getVisibility() !== 'student') {
                // No visible content here.
                continue;
            }

            if (
                // Use case-insensitive search to match behaviour of SQL LIKE condition.
                mb_stripos($field->getTitle(), $keyword, 0, self::ENCODING) !== false ||
                mb_stripos($field->getBody(), $keyword, 0, self::ENCODING) !== false
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

        $returnUrl = (
            // Note $this->request is the request to the search API, not the search page.
            // The return URL must point to the search page, with the same query string as was used in the search API request.
            $this->routeGenerator->generate('search.search') . '?' . $this->request->getUri()->getQuery()
        );

        $route = 'learningAssessment.getUpdateAssessmentForm';
        $params = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            // For quizzes and dialog agents, differentiate from 'Modify Contents'/'Configure Agent'.
            $modifyTitle = 'Modify';
            if ($assessment instanceof Assessment\Quiz\Model) {
                $modifyTitle = 'Modify Settings';
            } elseif ($assessment instanceof Assessment\DialogAgent\Model) {
                $modifyTitle = 'Modify Settings';
            }

            $links[] = [
                // All assessment types use the same URL for their 'Modify' page.
                'url' => $this->routeGenerator->generate($route, $params) . '?returnUrl=' . Redirect::encode($returnUrl),
                'title' => $modifyTitle,
                'icon' => 'icon-modify',
            ];
        }

        if ($assessment instanceof Assessment\Quiz\Model) {
            $route = 'learnosity.getContent';
            $params = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    // All assessment types use the same URL for their 'Modify' page.
                    'url' => $this->routeGenerator->generate($route, $params) . '?returnUrl=' . Redirect::encode($returnUrl),
                    'title' => 'Modify Contents',
                    'icon' => 'icon-modify',
                ];
            }
        }

        if ($assessment instanceof Assessment\DialogAgent\Model) {
            $route = 'learningDialogAgent.getUpdateForm';
            $params = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    // All assessment types use the same URL for their 'Modify' page.
                    'url' => $this->routeGenerator->generate($route, $params) . '?returnUrl=' . Redirect::encode($returnUrl),
                    'title' => 'Configure Agent',
                    'icon' => 'icon-modify',
                ];
            }
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

    /**
     * @param string       $type
     * @param RosettaStone $rosettaStone
     * @return string
     */
    public function getTypeTitleFromType(string $type, RosettaStone $rosettaStone): string
    {
        if ($type === self::BASE_TYPE) {
            return $rosettaStone->translate('assessment', false, true);
        }

        /** check if it matches any "assessment.{assessmentType}" */
        $split = explode('.', $type);
        $subType = $split[1] ?? '';
        if (self::BASE_TYPE === $split[0] && in_array($subType, AssessmentType::ALL_TYPES)) {
            return AssessmentType::stringToReadableName($subType);
        }

        return $type;
    }
}
