<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Ramsey\Uuid\Uuid;
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
        $filtersRaw = $this->getFiltersRawArray();

var_dump(
    json_encode(
        [
            'filtersRaw' => $filtersRaw,
            'filters' => $this->substituteTerminologyIntoFilters($filtersRaw)
        ]
    )
);
die;

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig", [
                'filters' => $this->substituteTerminologyIntoFilters($filtersRaw)
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

        $filtersRaw = [];

        if (true) {
            $filtersRaw[] = [
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
            $filtersRaw[] = ["title" => "Badges", "value" => "badges"];
        }

        if (true) {
            $filtersRaw[] = ["title" => "Events", "value" => "events"];
        }

        if (true) {
            $filtersRaw[] = [
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
            $filtersRaw[] = ["title" => "Forums", "value" => "forum"];
        }

        if (true) {
            $filtersRaw[] = ["title" => "Links", "value" => "link"];
        }

        if (true) {
            $filtersRaw[] = ["title" => "News", "value" => "news"];
        }

        if (true) {
            $filtersRaw[] = [
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
            $filtersRaw[] = ["title" => "Textboxes", "value" => "textbox"];
        }

        if (true) {
            $filtersRaw[] = ["title" => "Tiles", "value" => "tile"];
        }

        if (true) {
            $filtersRaw[] = [
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

        return $filtersRaw;
    }

    /**
     * @param array $filtersRaw
     * @return array
     */
    private function substituteTerminologyIntoFilters(array $filtersRaw): array
    {
        $filtersWithTerminology = [];

        foreach ($filtersRaw as $filterRaw) {
            $terminology = self::getTerminologyMatchingSingularForm(strtolower($filterRaw["title"]));

            $filterWithTerminology = [
                "title" => (($terminology === null) ? $filterRaw["title"]: $terminology["singular"]),
                "value" => $filterRaw["value"],
            ];

            if (array_key_exists('subTypes', $filterRaw) && count($filterRaw["subTypes"]) > 0) {
                $filterWithTerminology["subTypes"] = $this->substituteTerminologyIntoFilters($filterRaw["subTypes"]);
            }

            $filtersWithTerminology[] = $filterWithTerminology;
        }

        return $filtersWithTerminology;
    }

    /**
     * @param string $name
     * @return array|null
     */
    private static function getTerminologyMatchingSingularForm($name): array|null
    {
        static $terminologyBySingularForm = null;

        if ($terminologyBySingularForm === null) {
            $terminologyBySingularForm = [];

            foreach (self::$l10nMapper->findAll()->sortNaturally('title') as $termObj) {
                $terminologyBySingularForm[strtolower($termObj->getSingularForm())] = array_merge(
                    $termObj->allForms(),
                    ['term' => $termObj->getTerm()]
                );
            }
        }

        return (array_key_exists($name, $terminologyBySingularForm)) ? $terminologyBySingularForm[$name] : null;
    }
}
