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
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\Search\Api\Database\ResultType;
use Schoolbox\UserMapper;
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
     * @var UserMapper
     */
    protected static $userMapper;

    /**
     * Let's build this thing!
     */
    public function __construct(
        RosettaStone $rosettaStone,
        UserMapper $userMapper
    ) {
        self::$l10nMapper = L10nMapper::getInstance();
        self::$rosettaStone = $rosettaStone;
        self::$userMapper = $userMapper;
    }

    /**
     * Entry point for the main search page.
     *
     * @return void
     * @throws NotFound
     * @throws Error
     * @since 23.1
     * @api   GET /search
     */
    public function search(): void
    {
        $keywordFromUrl = $this->requestQuery->get('keyword');

        $dataObjectFactory = new SearchFilter\DataObjectFactory(self::$userMapper);

        try {
            $filterFromUrl = $dataObjectFactory->build($this->requestQuery['filter'] ?? null, null);
        } catch (Exception $e) {
            // The filter data in the URL was invalid, so ignore the data and create an empty filter.
            $filterFromUrl = $dataObjectFactory->build(null, null);
        }

        $filterFromUrlIsEmpty = (
            $filterFromUrl->fromDate === null &&
            $filterFromUrl->toDate === null &&
            $filterFromUrl->creator === null &&
            $filterFromUrl->types === null
        );

        $this->response = $this->responseFromTemplate(
            "@search/search.twig",
            [
                // List of filter types (used for building the empty form).
                'filterTypes' => $this->getFiltersRawArray(),

                'keywordValue' => $keywordFromUrl,
                'filterDateRangeValues' => (
                    ($filterFromUrlIsEmpty) // Only use the default date range if no filter was specified in the URL.
                    ? $this->getDefaultDateRangeFilterValues()
                    : [
                        'startDate' => $filterFromUrl->fromDate?->toRfc3339String(),
                        'endDate' => $filterFromUrl->toDate?->toRfc3339String()
                    ]
                ),
                'filterCreatorValue' => (
                    ($filterFromUrl->creator === null)
                    ? null
                    : [
                        'id' => $filterFromUrl->creator->getId(),
                        'name' => (
                            // The format of creator name must match that used by the Vue front-end.
                            $filterFromUrl->creator->getFullName() . ' - ' .
                            $filterFromUrl->creator->getRole()->getName() .
                            ' (' . $filterFromUrl->creator->getExternalId() . ')'
                        )
                    ]
                ),
                'filterTypesValues' => (
                    ($filterFromUrl->types === null)
                    ? []
                    : $filterFromUrl->types
                ),
                'resultPageSize' => (
                    (int)$this->getConfigLoader()->get(ConfigMapper::NAME_ADVANCED_SEARCH_RESULT_PAGE_SIZE)
                ),
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
        $loggedInUserRole = $this->getUser()->getRole();


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
            $assessmentTypes = AssessmentType::getEnabledOptions($configLoader);

            $subTypes = [];
            foreach ($assessmentTypes as $typeKey => $title) {
                $subTypes[] = [
                    "title" => $title,
                    "value" => sprintf("%s.%s", ResultType\Assessment\Hydrator::BASE_TYPE, $typeKey)
                ];
            }

            // NOTE: Features to include based on NAME_LMS_ENABLED were found by inspecting code in conf/modules/Learning.php.
            $filters[] = [
                "title" => "Learning " . $rosettaStone->translate("activity", false, true),
                "value" => ResultType\Assessment\Hydrator::BASE_TYPE,
                "subTypes" => $subTypes
            ];
        }

        if (
            // See similar check in Search/Api/Module->buildController().
            $configLoader->get(ConfigMapper::NAME_BADGES_ENABLED) &&
            ($loggedInUserRole->isStudent() || $loggedInUserRole->isStaff())
        ) {
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
                    \Alaress_Date_Format::now()->getStartOfSchoolYear()->startOfDay()->toRfc3339String();
                $endDate =
                    \Alaress_Date_Format::now()->endOfSchoolYear()->endOfDay()->toRfc3339String();
                break;
            case 1: // 1 year (6 months in the past, 6 months in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addMonths(-6)->startOfDay()->toRfc3339String();
                $endDate =
                    \Alaress_Date_Format::now()->addMonths(6)->endOfDay()->toRfc3339String();
                break;
            case 2: // 2 years (1 year in the past, 1 year in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addYears(-1)->startOfDay()->toRfc3339String();
                $endDate =
                    \Alaress_Date_Format::now()->addYears(1)->endOfDay()->toRfc3339String();
                break;
            case 3: // 5 years (4 years in the past, 1 year in the future)
                $startDate =
                    \Alaress_Date_Format::now()->addYears(-4)->startOfDay()->toRfc3339String();
                $endDate =
                    \Alaress_Date_Format::now()->addYears(1)->endOfDay()->toRfc3339String();
                break;
            default: // default to School year
                $startDate =
                    \Alaress_Date_Format::now()->getStartOfSchoolYear()->startOfDay()->toRfc3339String();
                $endDate =
                    \Alaress_Date_Format::now()->endOfSchoolYear()->endOfDay()->toRfc3339String();
                break;
        }
        return [
            "startDate" => $startDate,
            "endDate" => $endDate,
        ];
    }
}
