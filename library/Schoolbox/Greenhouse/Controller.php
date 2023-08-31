<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Ramsey\Uuid\Uuid;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws Error
     */
    public function getGreenhouse(string $templateName): void
    {
        $uuid = (string)Uuid::uuid4();

        new \Alaress_Probe($uuid, strlen($uuid));

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig", [
                'filters' => [
                    "Assessment" => [
                        "Due Work",
                        "Lesson Plan",
                        "Project",
                        "Quiz",
                        "Task",
                    ],
                    "Badges",
                    "Events",
                    "Files" => [
                        "Audio",
                        "Document",
                        "Image",
                        "Other",
                        "Video",
                    ],
                    "Forums",
                    "Links",
                    "News",
                    "Pages / Folders" => [
                        "Course/Unit",
                        "Current Class",
                        "Folder",
                        "Group",
                        "Homepage",
                    ],
                    "Textboxes",
                    "Tiles",
                    "Users" => [
                        "Guest",
                        "Parent",
                        "Staff",
                        "Student",
                    ]
                ]
            ]
        );
    }
}
