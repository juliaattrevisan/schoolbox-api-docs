<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
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
        $this->response = $this->responseFromTemplate('@greenhouse/' . $templateName . '.twig', [
        ]);
    }

    /**
     * TEMP until SA-19479 creates an endpoint for this
     */
    public function postLessonPlan(): void
    {
        /** @var array<string,string> - Each requested field ID mapped to its new content */
        $newFieldContent = [];

        $fields = $this->requestPost->get("fields");
        foreach ($fields as $field) {
            $newFieldContent[$field["id"]] = $field["body"] . " (but now with added gippity flavouring)";
        }

        // simulate thinking...
        sleep(1);

        $this->response = $this->responseAsJson($newFieldContent)->withStatus(200);
    }
}
