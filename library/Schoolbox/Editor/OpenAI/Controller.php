<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientExceptionInterface;
use Ramsey\Uuid\Uuid;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\OpenAi\Agent\ImageGeneration;
use Schoolbox\OpenAi\ClientFactory;
use Scrub;

/**
 * This controller acts as a proxy for OpenAI API.  It just appends the configured API key to the request in
 * order to keep it secret. Then forwards the request and sends the contents back to user.  This ensures that the
 * users are always communicating via our server, using our key and in a controlled way.
 *
 * Future enhancements may include:
 *  * Request logging
 *  * Guardrails to prevent inappropriate usage
 *  * Rate limiting
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private Client $client,
        private \Schoolbox\Error\Mapper $errorMapper,
        private Log\Mapper $logMapper,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper,
        private ClientFactory $clientFactory,
    ) {
    }


    /**
     * Generates a payload about lesson plans
     *
     * @return void
     * @throws ClientExceptionInterface
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function postLessonPlan(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new Config();
        $lpConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_LESSONPLAN));

        // if not enabled, 404
        if ($aiConfig->isEnabled($lpConfig) === false) {
            $returnCode = 404;

            $problem = new ApiProblem("Lesson Plan AI is not enabled.")
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }

        try {
            $helper = new Helper(
                trim($lpConfig[Config::FIELD_PROVIDER]['value']),
                trim($lpConfig[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_AIKEY]['value']),
                trim($lpConfig[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_RESOURCE_NAME]['value'] ?? ''),
                trim($lpConfig[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value'] ?? ''),
                trim($lpConfig[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value'] ?? ''),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            );

            $payloadGenerator = (new Payload\LessonPlan(
                trim($lpConfig[Config::FIELD_PROVIDER]['value']),
                trim($lpConfig[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value'] ?? ''),
                trim($lpConfig[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value'] ?? ''),
                trim($lpConfig[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_SYSTEM_PROMPT]['value'] ?? ''),
                trim((string)$this->getConfigLoader()->get('spellcheck_language')),
                $this->curriculumNodeMapper
            ));

            /* Generate a payload for ai, using the prompt pieces for System and User info
             * From a POST payload
             */
            $inputs = [
                'templateTitle' => $this->requestPost->get('templateTitle'),
                'lessonPlanTitle' => $this->requestPost->get('lessonPlanTitle'),
                'context' => $this->requestPost->get('context'),
                'fields' => $this->requestPost->get('fields'),
                'duration' => $this->requestPost->get('duration'),
                'curriculumNodeIds' => $this->requestPost->get('curriculumNodeIds'),
                'additionalContext' => $this->requestPost->get('additionalContext') ?? '',
                'generateForField' => $this->requestPost->get('generateForField') ?? null,
            ];

//            $inputs = [
//                'templateTitle' => 'MYP Unit Planner',
//                'lessonPlanTitle' => 'Scott Science',
//                'context' => 'Year 8 Science',
//                'fields' => [
//                    [
//                        'id' => 'Learning Objectives',
//                        'title' => 'Learning Objectives',
//                        'description' => 'What students should learn from the lesson',
//                        'visibility' => 'student',
//                        'body' => '',
//                        'selected' => true
//                    ]
//                ],
//                'duration' => '17 minutes',
//                'curriculumNodeIds' => [
//                    4156, 4157, 4160
//                ],
//                'additionalContext' => '',
//            ];

            $payload = $payloadGenerator->generate(
                $inputs['templateTitle'],
                $inputs['lessonPlanTitle'],
                $inputs['context'],
                $inputs['fields'],
                $inputs['duration'],
                $inputs['curriculumNodeIds'],
                $inputs['additionalContext'],
                $inputs['generateForField'],
            );

            /** gen a sessionID guid for keeping track of what is happening
             * for lessonplan each ai request is a new session
             */
            $aiSessionId = (string)Uuid::uuid4();

            try {
                /* Send it and decode the result (if any: sometimes it returns null)
                 * and dodge around all its weird encoding business with a double-decode,
                 * and ditching all its training markdown (lol) that it loves to use
                 *
                 * https://community.openai.com/t/json-returning-with-json/584818/6
                 */
                $data = $helper->request($this->getUser(), $payload, Helper::FEATURE_LESSONPLAN, $aiSessionId);
            } catch (SchoolboxException $e) {
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus($e->getCode());

                return;
            }
            /* The actual output we requested is in this chain of objects, for now assume it'll only ever be one */
            $newFieldData = json_decode($data['output'][0]['content'][0]['text'], true);

            /* if this didn't decode correctly (i.e. null) then some weird response came from the AI
             * send that on... so we can figure out what happened
             */
            if ($newFieldData === null) {
                $returnCode = 500;

                $this->response = $this->responseAsJson([
                    'output' => $data["output"]
                ])->withStatus($returnCode);

                return;
            }

            /* Since we request the fields to be keyed by their title, not ID for AI reasons,
             * translate it back to IDs for frontend sanity
             */
            $fields = [];
            foreach ($inputs['fields'] as $field) {
                // maybe the AI didn't return stuff for this field... if so, the frontend will handle that.
                $fieldKey = $payloadGenerator->getPayloadFieldKey($inputs['fields'], $field['id']);
                if (isset($newFieldData[$fieldKey])) {
                    $fields[$field['id']] = Scrub::html($newFieldData[$fieldKey]);
                }
            }

            /* if the AI didn't return anything, then we don't want to send an empty field back
             * just send whatever on earth the AI sent back to us, it'll be wrong... but it'll be something
             */
            if (empty($fields)) {
                $fields = $newFieldData;
            }

            $this->response = $this->responseAsJson($fields);

            return;
        } catch (\DomainException $e) {
            /* Not configured? Not found
             */
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }


    /**
     * Generates an image as part of a conversation
     *
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function postImage(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // some images may take quite some time, avoid the request stopping early
        $this->extendExecutionTime();

        try {
            // use the frontend's aiSessionId to keep track of conversations
            $sessionId = $this->requestPost->get('aiSessionId');
            // we should have an aiSessionId generated by the frontend.. if not, we should error,
            // this shouldn't happen, it's probably someone manually hitting the API, send them this error.
            if (!$sessionId || !Uuid::isValid($sessionId)) {
                $problem = (new ApiProblem("AI Session id is required and must be a valid UUID."))
                    ->setStatus(400)
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus(400);

                return;
            }

            $agent = new ImageGeneration(
                $this->clientFactory->buildForFeature(Helper::FEATURE_IMAGE),
                $this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_MODEL),
                $this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_OUTPUT_QUALITY) ?? 'low',
                $this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_SYSTEM_PROMPT),
            );
            try {
                // Size and style need to be converted
                $response = $agent->requestStream(
                    $this->getUser(),
                    $this->requestPost->get('userPrompt'),
                    Helper::FEATURE_IMAGE,
                    $sessionId,
                    size: ($this->requestPost->get('attributes') ?? [])['size'] ?? null,
                    style: ($this->requestPost->get('attributes') ?? [])['style'] ?? null,
                    imageUrl: $this->requestPost->get('imageUrl') ?? null,
                    previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
                );

                // The iterable response can just be passed straight to
                // responseFromStream
                $this->response = $this->responseFromStream(Utils::streamFor($response))
                    ->withHeader('Content-Type', 'text/event-stream')
                    ->withHeader('Cache-Control', 'no-cache')
                    ->withHeader('Connection', 'keep-alive')
                    ->withHeader('Content-Encoding', 'none');
                return;
            } catch (SchoolboxException $e) {
                // This will always be a stream
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                $this->response = $this
                    ->responseFromContent(printf("event: error\ndata: %s\n\n", json_encode($problem)))
                    ->withStatus($e->getCode())
                    ->withHeader('Content-Type', 'text/event-stream')
                    ->withHeader('Cache-Control', 'no-cache')
                    ->withHeader('Connection', 'keep-alive')
                    ->withHeader('Content-Encoding', 'none');
                return;
            }
        } catch (\DomainException $e) {
            // This will always be a stream
            /* Not configured? Not found
             */
            $returnCode = 404;
            $problem    = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this
                ->responseFromContent(sprintf("event: error\ndata: %s\n\n", json_encode($problem)))
                ->withStatus($e->getCode())
                ->withHeader('Content-Type', 'text/event-stream')
                ->withHeader('Cache-Control', 'no-cache')
                ->withHeader('Connection', 'keep-alive')
                ->withHeader('Content-Encoding', 'none');

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }


    /**
     * This is used by ckeditor's Little Robot Dude ai assistant which posts
     * to /openai route via some direct javascript thing
     *
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function postAiAssistant(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new Config();
        $chatConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_TEXT_CHAT));

        // if not enabled, 404
        if ($aiConfig->isEnabled($chatConfig) === false) {
            $returnCode = 404;

            $problem = (new ApiProblem("AI Assistant is not enabled."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }

        /** gen a sessionID guid for keeping track of what is happening
         * for ckeditor chat each ai request is a new session
         */
        $aiSessionId = (string)Uuid::uuid4();

        try {
            (new Helper(
                trim($chatConfig[Config::FIELD_PROVIDER]['value']),
                trim($chatConfig[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_AIKEY]['value']),
                trim($chatConfig[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_RESOURCE_NAME]['value'] ?? ''),
                trim($chatConfig[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value'] ?? ''),
                trim($chatConfig[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value'] ?? ''),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            ))->aiAssistantChat($this->getUser(), $this->request->getBody()->getContents(), $chatConfig[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_SYSTEM_PROMPT]['value'], $aiSessionId);
        } catch (\DomainException $e) {
            /* Not configured? Not found
             */
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }
}
