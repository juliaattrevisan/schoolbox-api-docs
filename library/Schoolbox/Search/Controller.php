<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Search;

use Alaress\Web\Controller\Base;
use Exception;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStone;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * @var L10nMapper
     */
    protected static $l10nMapper;

    /**
     * @var RosettaStone
     */
    protected static $rosettaStone;

    /**
     * Let's build this thing!
     */
    public function __construct(
        RosettaStone $rosettaStone
    ) {
        self::$l10nMapper = L10nMapper::getInstance();
        self::$rosettaStone = $rosettaStone;
    }

    /**
     * Entry point for the main search page.
     *
     * @api GET /search
     * @since 23.1
     * @throws Error
     * @return void
     */
    public function search(): void
    {
        $keywordFromUrl = $this->requestQuery->get('keyword');
        $filterFromUrlJson = $this->requestQuery->get('filter');

        try {
            $filterFromUrl = json_decode($filterFromUrlJson, true);
        } catch (Exception $e) {
            // Could not decode JSON from URL, so ignore.
            $filterFromUrl = null;
        }

        $filterFromUrl = null; // TODO: Get rid of this.

        // TODO: Validate $filterFromUrl?

        $this->response = $this->responseFromTemplate(
            "@search/search.twig",
            [
                // List of filter types (used for building the empty form).
                'filterTypes' => $this->getFiltersRawArray(),

                'keywordValue' => $keywordFromUrl,
                'filterDateRangeValues' => (
                    ($filterFromUrl !== null)
                    ? [
                        'startDate' => $filterFromUrl['date']['from'],
                        'endDate' => $filterFromUrl['date']['to']
                    ]
                    : $this->getDefaultDateRangeFilterValues()
                ),
                'filterCreator' => (
                    ($filterFromUrl !== null)
                    ? $filterFromUrl['creator']
                    : null
                ),
                'filterTypesValues' => (
                    ($filterFromUrl !== null)
                    ? $filterFromUrl['types']
                    : []
                )
            ]
        );
    }

    /**
     * @return array - Array of filter names indexed by group name.
     */
    private function getFiltersRawArray(): array
    {
        // The hard-coded filters list returned by this function originated in https://schoolbox.atlassian.net/browse/SA-7782.

        $rosettaStone = self::$rosettaStone;

        // NOTE: The values for the 'value' key must match those in ResultTypes.ts.
        $filters = [
            ["title" => $rosettaStone->translate("news", false, true), "value" => "news"],
            ["title" => "Blogs", "value" => "blog"],
            ["title" => "Events", "value" => "event"],
            ["title" => "Forums", "value" => "forum"],
            ["title" => "Links", "value" => "link"],
            ["title" => "Textboxes", "value" => "textbox"],
            ["title" => "Tiles", "value" => "tile"],
        ];

        $configLoader = $this->getConfigLoader();

        if ($configLoader->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $subTypes = [
                ["title" => $rosettaStone->translate("dueWork", false, true), "value" => "assessment.dueWork"],
                ["title" => $rosettaStone->translate("project", false, true), "value" => "assessment.project"],
                ["title" => "Lesson Plan", "value" => "assessment.lessonPlan"],
                ["title" => "Quiz", "value" => "assessment.quiz"],
                ["title" => "Task", "value" => "assessment.task"],
            ];

            if ($configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE)) {
                $subTypes[] = ["title" => "3rd Party Assessments", "value" => "assessment.lti"];
            }

            // NOTE: Features to include based on NAME_LMS_ENABLED were found by inspecting code in conf/modules/Learning.php.
            $filters[] = [
                "title" => "Learning " . $rosettaStone->translate("activity", false, true),
                "value" => "assessment",
                "subTypes" => $subTypes
            ];
        }

        if ($configLoader->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $filters[] = ["title" => "Badges", "value" => "badge"];
        }

        $filters[] = [
            "title" => "Files",
            "value" => "file",
            "subTypes" => [
                ["title" => "Audio", "value" => "file.audio"],
                ["title" => "Document", "value" => "file.document"],
                ["title" => "Image", "value" => "file.image"],
                ["title" => "Other", "value" => "file.other"],
                ["title" => "Video", "value" => "file.video"],
            ]
        ];

        $pagesFoldersSubTypes = [
            ["title" => "Current " . $rosettaStone->translate("class", true, true), "value" => "page.class"],
            ["title" => "Folders", "value" => "page.folder"],
            ["title" => "Groups", "value" => "page.group"],
            ["title" => "Homepages", "value" => "page.homepage"],
        ];

        if ($configLoader->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $pagesFoldersSubTypes[] = ["title" => "Courses", "value" => "page.course"];
            $pagesFoldersSubTypes[] = ["title" => $rosettaStone->translate("unit", true, true), "value" => "page.unit"];
        }

        $filters[] = [
            "title" => "Pages / Folders",
            "value" => "page",
            "subTypes" => $pagesFoldersSubTypes
        ];

        $userSubTypes = [
            ["title" => "Staff", "value" => "user.staff"],
            ["title" => "Guest", "value" => "user.guest"]
        ];

        $loggedInUserRole = $this->getUser()->getRole();

        if (
            $loggedInUserRole->isStaff() || (
                ($loggedInUserRole->isStudent() || $loggedInUserRole->isParent()) &&
                $configLoader->get('student_search_enable')
            )
        ) {
            $userSubTypes[] = ["title" => "Student", "value" => "user.student"];
        }

        if (
            $loggedInUserRole->isStaff() || (
                ($loggedInUserRole->isStudent() || $loggedInUserRole->isParent()) &&
                $configLoader->get('parent_search_enable')
            )
        ) {
            $userSubTypes[] = ["title" => "Parent", "value" => "user.parent"];
        }

        $filters[] = [
            "title" => "Users",
            "value" => "user",
            "subTypes" => $userSubTypes
        ];

        // Sort top-level filters.
        // Note that sorting is necessary here because we cannot hard-code the order given that we are using
        // the $rosettaStone to translate hard-coded filter names into localised names.
        usort($filters, [$this, 'compareFilterArraysForSortingPurposes']);

        // Sort second-level filters (subTypes).
        // Sorting is necessary for the reason given above.
        foreach ($filters as &$filter) {
            if (array_key_exists('subTypes', $filter)) {
                usort($filter['subTypes'], [$this, 'compareFilterArraysForSortingPurposes']);
            }
        }

        return $filters;
    }

    /**
     * @param $filterA
     * @param $filterB
     * @return int
     */
    public static function compareFilterArraysForSortingPurposes($filterA, $filterB): int
    {
        return strnatcmp($filterA["title"], $filterB["title"]);
    }

    /**
     * To set the default date range filter
     * @return array - { startDate: string, endDate: string }
     */
    private function getDefaultDateRangeFilterValues(): array
    {
        switch ($this->getConfigLoader()->get('date_range_search_filter')) {
            case 0: // School year
                $startDate =
                    \Alaress_Date_Format::now()->getStartOfSchoolYear()->startOfDay()->getJson();
                $endDate =
                    \Alaress_Date_Format::now()->endOfSchoolYear()->endOfDay()->getJson();
                break;
            case 1: // 1 year (6 months in the past, 6 months in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addMonths(-6)->startOfDay()->getJson();
                $endDate =
                    \Alaress_Date_Format::now()->addMonths(6)->endOfDay()->getJson();
                break;
            case 2: // 2 years (1 year in the past, 1 year in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addYears(-1)->startOfDay()->getJson();
                $endDate =
                    \Alaress_Date_Format::now()->addYears(1)->endOfDay()->getJson();
                break;
            case 3: // 5 years (2.5 years in the past, 2.5 years in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addYears(-2)->addMonths(-6)->startOfDay()->getJson();
                $endDate =
                    \Alaress_Date_Format::now()->addYears(2)->addMonths(6)->endOfDay()->getJson();
                break;
            default: // default to School year
                $startDate =
                    \Alaress_Date_Format::now()->getStartOfSchoolYear()->startOfDay()->getJson();
                $endDate =
                    \Alaress_Date_Format::now()->endOfSchoolYear()->endOfDay()->getJson();
                break;
        }
        return [
            "startDate" => $startDate,
            "endDate" => $endDate,
        ];
    }
}
