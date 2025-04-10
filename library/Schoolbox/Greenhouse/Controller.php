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
        $payload = [
            "model" => "gpt-4o",
            "input" => [
                [
                    "role" => "system",
                    "content" =>
                        "You are a qualified teacher looking to generate a lesson plan for year 9 science.",
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Create lesson plans aligned to the curriculum areas listed below.\n\nVictorian Curriculum v2.0\nLearning areas\nScience\nScience Understanding\nEarth and space sciences\nLevels 7 and 8\nkey processes of the rock cycle occur over different timescales; the properties of sedimentary, igneous and metamorphic rocks not only reflect their formation but also impact their usefulness and determine the methods used when mined\nVictorian Curriculum v2.0\nLearning areas\nScience\nScience Inquiry\nCommunicating\nLevels 7 and 8\ncommunicating ideas, findings and arguments for specific purposes and audiences involves the selection and use of appropriate presentation formats, scientific vocabulary, models and other representations, and may include the use of digital tools\n",
                ],
            ],
            "max_output_tokens" => 2000,
            "temperature" => 1,
            "top_p" => 1,
            "stream" => false,
            "text" => [
                "format" => [
                    "type" => "json_schema",
                    "name" => "fields_format",
                    "schema" => [
                        "type" => "object",
                        "properties" => [
                            "1" => [
                                "type" => "string",
                                "description" => "Learning Objectives: What students should learn from the lesson\r\n",
                            ],
                            "2" => [
                                "type" => "string",
                                "description" =>
                                    "Lesson Structure and Activities: A detailed outline of the lesson with activities and teacher notes. Activities can include direct teaching as well as activities for students to complete. \r\n",
                            ],
                            "3" => [
                                "type" => "string",
                                "description" =>
                                    "Differentiation: Describe the possible differentiation approaches for students with learning difficulties, including, Dyslexia, Dysgraphia, Auditory Processing Disorder\r\n",
                            ],
                            "4" => [
                                "type" => "string",
                                "description" => "Resources: Include resources which will support the delivery of this lesson\r\n",
                            ],
                            "5" => [
                                "type" => "string",
                                "description" =>
                                    "Checks for Understanding: Provide detail on how to check for student understanding according to the learning objectives",
                            ],
                        ],
                        "required" => ["1", "2", "3", "4", "5"],
                        "additionalProperties" => false,
                    ],
                    "strict" => true,
                ],
            ],
        ];

        $endpoint = "https://api.openai.com/v1/responses";
        $token = $this->getConfigLoader()->get('openai_key');

        // do a request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        /** @var array<string,string> - Each requested field ID mapped to its new content */
        $newFieldContent = [];

        // $fields = $this->requestPost->get("fields");
        // foreach ($fields as $field) {
        //     $newFieldContent[$field["id"]] = $field["body"] . " (but now with added gippity flavouring)";
        // }

        // the fields end up in $responseData["output"][0]["content"][0]["text"]
        // although i expect there's more to that, and you may need to find the right ones in the lists, if there could be many

        $fieldResponse = json_decode($responseData["output"][0]["content"][0]["text"], true);

        // idk just shove the keys in there for now
        foreach ($fieldResponse as $key => $value) {
            $newFieldContent[$key] = $value;
        }

        $this->response = $this->responseAsJson($newFieldContent)->withStatus(200);
    }
}
