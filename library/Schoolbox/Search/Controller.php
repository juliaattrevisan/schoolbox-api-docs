<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Search;

use Alaress\Web\Controller\Base;
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
     * @throws Error
     */
    public function search(): void
    {
        $this->response = $this->responseFromTemplate(
            "@search/search.twig",
            [
                'filters' => $this->getFiltersRawArray()
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
        $rosettaStone = self::$rosettaStone;

        if ($configLoader->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $subTypes = [
                ["title" => $rosettaStone->translate("dueWork", false, true), "value" => "assessment.dueWork"],
                ["title" => $rosettaStone->translate("project", false, true), "value" => "assessment.project"],
                ["title" => "Lesson Plan", "value" => "assessment.lessonPlan"],
                ["title" => "Quiz"       , "value" => "assessment.quiz"      ],
                ["title" => "Task"       , "value" => "assessment.task"      ],
            ];

            if ($configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE)) {
                $subTypes[] = ["title" => "LTI", "value" => "assessment.ltiAssessment"];
            }

            // NOTE: Features to include based on NAME_LMS_ENABLED were found by inspecting code in conf/modules/Learning.php.
            $filters[] = [
                "title" => $rosettaStone->translate("assessment", false, true),
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

        $pagesFoldersSubTypes = [
            ["title" => "Current " . $rosettaStone->translate("class", false, true), "value" => "page.class"],
            ["title" => "Folder"  , "value" => "page.other"   ],
            ["title" => "Group"   , "value" => "page.other"   ],
            ["title" => "Homepage", "value" => "page.homepage"],
        ];

        if ($configLoader->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $pagesFoldersSubTypes[] = ["title" => "Course", "value" => "page.course"];
            $pagesFoldersSubTypes[] = ["title" => $rosettaStone->translate("unit", false, true), "value" => "page.unit"];
        }

        $filters[] = [
            "title" => "Pages / Folders",
            "value" => "page",
            "subTypes" => $pagesFoldersSubTypes
        ];

        $userSubTypes = [
            ["title" => "Guest", "value" => "user.guest"],
            ["title" => "Staff", "value" => "user.staff"],
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

        $filters[] = ["title" => $rosettaStone->translate("news", false, true), "value" => "news"];
        $filters[] = ["title" => "Events", "value" => "events"];
        $filters[] = ["title" => "Forums", "value" => "forum"];
        $filters[] = ["title" => "Links", "value" => "link"];
        $filters[] = ["title" => "Textboxes", "value" => "textbox"];
        $filters[] = ["title" => "Tiles", "value" => "tile"];

        return $filters;
    }
}
