<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Crell\ApiProblem\ApiProblem;
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


    // /**
    //  * TEMP until SA-19479 creates an endpoint for this
    //  */
    // public function postLessonPlan(): void
    // {
    //     try {
    //         /** @var array<string,string> - Each requested field ID mapped to its new content */
    //         $newFieldContent = [];

    //         $fields = $this->requestPost->get("fields");
    //         foreach ($fields as $field) {
    //             $newFieldContent[$field["id"]] = $field["body"] . " (but now with added gippity flavouring)";
    //         }

    //         // simulate thinking...
    //         sleep(1);

    //         // but what if it died ?
    //         throw new \Exception('oh no', 500);

    //         $this->response = $this->responseAsJson($newFieldContent)->withStatus(200);
    //     } catch (\Exception $e) {
    //         $problem = (new ApiProblem($e->getMessage()))
    //             ->setStatus($e->getCode())
    //             ->asArray();
    //         $this->response = $this->responseAsJson($problem)
    //             ->withStatus($e->getCode());
    //         return;
    //     }
    // }

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
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "You are a qualified teacher looking to generate a lesson plan for year 9 science. Your response must be a JSON object matching the defined schema. Do not include any extra keys or text outside of the JSON object. Each field key should map to a string that can include HTML formatting.",
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Create lesson plans aligned to the curriculum areas listed below.\n\nVictorian Curriculum v2.0\nLearning areas\nScience\nScience Understanding\nEarth and space sciences\nLevels 7 and 8\nkey processes of the rock cycle occur over different timescales; the properties of sedimentary, igneous and metamorphic rocks not only reflect their formation but also impact their usefulness and determine the methods used when mined\nVictorian Curriculum v2.0\nLearning areas\nScience\nScience Inquiry\nCommunicating\nLevels 7 and 8\ncommunicating ideas, findings and arguments for specific purposes and audiences involves the selection and use of appropriate presentation formats, scientific vocabulary, models and other representations, and may include the use of digital tools\n",
                ],
            ],
            "max_tokens" => 2000,
            "temperature" => 1,
            "top_p" => 1,
            "stream" => false,
            "format" => [
                "type" => "json_schema",
                "name" => "fields_format",
                "schema" => [
                    "type" => "object",
                    "properties" => [
                        "Learning Objectives" => [
                            "type" => "string",
                            "description" => ": What students should learn from the lesson\r\n",
                        ],
                        "Lesson Structure and Activities" => [
                            "type" => "string",
                            "description" =>
                                "A detailed outline of the lesson with activities and teacher notes. Activities can include direct teaching as well as activities for students to complete. \r\n",
                        ],
                        "Differentiation" => [
                            "type" => "string",
                            "description" =>
                                "Describe the possible differentiation approaches for students with learning difficulties, including, Dyslexia, Dysgraphia, Auditory Processing Disorder\r\n",
                        ],
                        "Resources" => [
                            "type" => "string",
                            "description" => "Include resources which will support the delivery of this lesson\r\n",
                        ],
                        "Checks for Understanding" => [
                            "type" => "string",
                            "description" =>
                                "Provide detail on how to check for student understanding according to the learning objectives",
                        ],
                    ],
                    "required" => ["Learning Objectives", "Lesson Structure and Activities", "Differentiation", "Resources", "Checks for Understanding"],
                    "additionalProperties" => false,
                ],
                "strict" => true,
            ],
        ];

        $endpoint = "https://api.openai.com/v1/chat/completions";
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
        // $newFieldContent = [];

        // $fields = $this->requestPost->get("fields");
        // foreach ($fields as $field) {
        //     $newFieldContent[$field["id"]] = $field["body"] . " (but now with added gippity flavouring)";
        // }

        // the fields end up in $responseData["output"][0]["content"][0]["text"]
        // although i expect there's more to that, and you may need to find the right ones in the lists, if there could be many

        $fieldResponse = json_decode($responseData["output"][0]["content"][0]["text"], true);

        $this->response = $this->responseAsJson($fieldResponse)->withStatus(200);
    }
}
