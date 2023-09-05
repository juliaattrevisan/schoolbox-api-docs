<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\Mapper as L10nMapper;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * @var L10nMapper
     */
    protected static $l10nMapper;

    /**
     * Let's build this thing!
     */
    public function __construct()
    {
        self::$l10nMapper = L10nMapper::getInstance();
    }

    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws Error
     */
    public function getGreenhouse(string $templateName): void
    {
        $filters = $this->getFiltersRawArray();

//var_dump(
//    json_encode(
//        [
//            'filters' => $this->substituteLocalisedTermsIntoFilters($filters)
//        ]
//    )
//);
//die;

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig",
            [
                'filters' => $this->substituteLocalisedTermsIntoFilters($filters)
            ]
        );
    }

    /**
     * @return array - Array of filter names indexed by group name.
     */
    private function getFiltersRawArray(): array
    {
        // The hard-coded filters list returned by this function originated in https://schoolbox.atlassian.net/browse/SA-7782.

        $filters = [];

        $loggedInUserRole = $this->getUser()->getRole();
        $configLoader = $this->getConfigLoader();

        if ($configLoader->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $subTypes = [
                ["title" => "Due Work"   , "value" => "assessment.dueWork"      ],
                ["title" => "Lesson Plan", "value" => "assessment.lessonPlan"   ],
                ["title" => "Project"    , "value" => "assessment.project"      ],
                ["title" => "Quiz"       , "value" => "assessment.quiz"         ],
                ["title" => "Task"       , "value" => "assessment.task"         ],
            ];

            if ($configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE)) {
                $subTypes[] = ["title" => "LTI", "value" => "assessment.ltiAssessment"];
            }

            // NOTE: Features to include based on NAME_LMS_ENABLED were found by inspecting code in conf/modules/Learning.php.
            $filters[] = [
                "title" => "Assessment",
                "value" => "assessment",
                "subTypes" => $subTypes
            ];
        }

        if ($configLoader->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $filters[] = ["title" => "Badges", "value" => "badges"];
        }

        $filters[] = [
            "title" => "Files",
            "value" => "file",
            "subTypes" => [
                ["title" => "Audio"   , "value" => "file.audio"   ],
                ["title" => "Document", "value" => "file.document"],
                ["title" => "Image"   , "value" => "file.image"   ],
                ["title" => "Other"   , "value" => "file.other"   ],
                ["title" => "Video"   , "value" => "file.video"   ],
            ]
        ];

        $filters[] = [
            "title" => "Pages / Folders",
            "value" => "page",
            "subTypes" => [
                ["title" => "Course"       , "value" => "page.course"  ],
                ["title" => "Unit"         , "value" => "page.unit"    ],
                ["title" => "Current Class", "value" => "page.class"   ],
                ["title" => "Folder"       , "value" => "page.other"   ],
                ["title" => "Group"        , "value" => "page.other"   ],
                ["title" => "Homepage"     , "value" => "page.homepage"],
            ]
        ];

        // TODO: Should user type be included in filters if no users of that type exist?
        $userSubTypes = [
            ["title" => "Guest", "value" => "user.guest" ],
            ["title" => "Staff", "value" => "user.staff" ],
        ];

        if ($loggedInUserRole->isStudent() || $loggedInUserRole->isParent()) {
            if ($configLoader->get('student_search_enable')) {
                $userSubTypes[] = ["title" => "Student", "value" => "user.student"];
            }

            if ($configLoader->get('parent_search_enable')) {
                $userSubTypes[] = ["title" => "Parent" , "value" => "user.parent"];
            }
        }

        $filters[] = [
            "title" => "Users",
            "value" => "user",
            "subTypes" => $userSubTypes
        ];

        $filters[] = ["title" => "Events", "value" => "events"];
        $filters[] = ["title" => "Forums", "value" => "forum"];
        $filters[] = ["title" => "Links", "value" => "link"];
        $filters[] = ["title" => "News", "value" => "news"];
        $filters[] = ["title" => "Textboxes", "value" => "textbox"];
        $filters[] = ["title" => "Tiles", "value" => "tile"];

        return $filters;
    }

    /**
     * For each title in $filters, look for a row in the schoolbox.localisation DB table with a matching title.
     * If one is found, replace the title in $filters with schoolbox.localisation.singular_form.
     * @param array $filters
     * @return array
     */
    private function substituteLocalisedTermsIntoFilters(array $filters): array
    {
        $filtersLocalised = [];

        foreach ($filters as $filter) {
            $singularForm = self::getSingularFormMatchingTitle(strtolower($filter["title"]));

            $filterWithTerminology = [
                "title" => (($singularForm === null) ? $filter["title"] : ucwords($singularForm)),
                "value" => $filter["value"],
            ];

            if (array_key_exists('subTypes', $filter) && count($filter["subTypes"]) > 0) {
                $filterWithTerminology["subTypes"] = $this->substituteLocalisedTermsIntoFilters($filter["subTypes"]);
            }

            $filtersLocalised[] = $filterWithTerminology;
        }

        return $filtersLocalised;
    }

    /**
     * @param string $title
     * @return string|null
     */
    private static function getSingularFormMatchingTitle(string $title): string|null
    {
        static $singularFormByTitle = null;

        if ($singularFormByTitle === null) {
            $singularFormByTitle = [];

            foreach (self::$l10nMapper->findAll()->sortNaturally('title') as $termObj) {
                $singularFormByTitle[strtolower($termObj->getTitle())] = $termObj->getSingularForm();
            }
        }

        return (array_key_exists($title, $singularFormByTitle)) ? $singularFormByTitle[$title] : null;
    }
}
