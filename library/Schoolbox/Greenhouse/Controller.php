<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
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
     * Lets build this thing!
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

var_dump(
    json_encode(
        [
            'filters' => $this->substituteLocalisedTermsIntoFilters($filters)
        ]
    )
);
die;

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig", [
                'filters' => $this->substituteLocalisedTermsIntoFilters($filters)
            ]
        );
    }

    /**
     * @return array - Array of filter names indexed by group name.
     */
    private function getFiltersRawArray(): array
    {
        // The hard-coded filters list returned by this function comes from https://schoolbox.atlassian.net/browse/SA-7782.

        // TODO: All the feature toggles relevant to filters are used in conf/modules/Learning.php.

        $filters = [];

        if (true) {
            $filters[] = [
                "title" => "Assessment",
                "value" => "assessment",
                "subTypes" => [
                    ["title" => "Due Work"   , "value" => "assessment.dueWork"      ],
                    ["title" => "Lesson Plan", "value" => "assessment.lessonPlan"   ],
                    ["title" => "LTI"        , "value" => "assessment.ltiAssessment"],
                    ["title" => "Project"    , "value" => "assessment.project"      ],
                    ["title" => "Quiz"       , "value" => "assessment.quiz"         ],
                    ["title" => "Task"       , "value" => "assessment.task"         ],
                ]
            ];
        }

        if (true) { // Toggle in Modules > Home (Enable Badges Module)
            $filters[] = ["title" => "Badges", "value" => "badges"];
        }

        if (true) {
            $filters[] = ["title" => "Events", "value" => "events"];
        }

        if (true) {
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
        }

        if (true) {
            $filters[] = ["title" => "Forums", "value" => "forum"];
        }

        if (true) {
            $filters[] = ["title" => "Links", "value" => "link"];
        }

        if (true) {
            $filters[] = ["title" => "News", "value" => "news"];
        }

        if (true) {
            $filters[] = [
                "title" => "Pages / Folders",
                "value" => "page",
                "subTypes" => [
                    ["title" => "Course"       , "value" => "page.course"  ], // Permission setting for this maybe (check feature matrix used by marketing) See Schoolbox Plan in System Settings -> Site
                    ["title" => "Unit"         , "value" => "page.unit"    ],
                    ["title" => "Current Class", "value" => "page.class"   ],
                    ["title" => "Folder"       , "value" => "page.other"   ],
                    ["title" => "Group"        , "value" => "page.other"   ],
                    ["title" => "Homepage"     , "value" => "page.homepage"],
                ]
            ];
        }

        if (true) {
            $filters[] = ["title" => "Textboxes", "value" => "textbox"];
        }

        if (true) {
            $filters[] = ["title" => "Tiles", "value" => "tile"];
        }

        if (true) {
            $filters[] = [
                "title" => "Users",
                "value" => "user",
                "subTypes" => [
                    // Show option if no users of that type exist?
                    ["title" => "Guest"  , "value" => "user.guest"  ],
                    ["title" => "Parent" , "value" => "user.parent" ],
                    ["title" => "Staff"  , "value" => "user.staff"  ],
                    ["title" => "Student", "value" => "user.student"],
                ]
            ];
        }

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
                "title" => (($singularForm === null) ? $filter["title"]: $singularForm),
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
