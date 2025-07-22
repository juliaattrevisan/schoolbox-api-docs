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
use Psr\Http\Client\ClientExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
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
        try {
            $helper = new Helper(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_KEY)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_RESOURCE_NAME)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_DEPLOYMENT_ID)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_MODEL)),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            );

            $payloadGenerator = (new Payload\LessonPlan(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_MODEL)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_DEPLOYMENT_ID)),
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

            try {
                /* Send it and decode the result (if any: sometimes it returns null)
                 * and dodge around all its weird encoding business with a double-decode,
                 * and ditching all its training markdown (lol) that it loves to use
                 *
                 * https://community.openai.com/t/json-returning-with-json/584818/6
                 */
                $data = $helper->request($this->getUser(), $payload);
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
        try {
            $helper = new Helper(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_KEY)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_RESOURCE_NAME)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_DEPLOYMENT_ID)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_MODEL)),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            );

            $payloadGenerator = (new Payload\ImageGeneration(
                $helper->getModel(),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_IMAGE_SYSTEM_PROMPT)),
            ));

            $payload = $payloadGenerator->generate(
                $this->requestPost->get('userPrompt'),
                $this->requestPost->get('attributes') ?? [],
                $this->requestPost->get('previousResponseId') ?? null,
            );

            try {
                $helper->request($this->getUser(), $payload);
            } catch (SchoolboxException $e) {
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                // If we've indicated that this is a streaming request, send it in the right format
                if (headers_sent()) {
                    echo sprintf("event: error\ndata: %s\n\n", json_encode($problem));
                    return;
                }

                $this->response = $this->responseAsJson($problem)
                    ->withStatus($e->getCode());

                return;
            }

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
     * This is used by ckeditor's Little Robot Dude ai assistant which posts
     * to /openai route via some direct javascript thing
     *
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function postAiAssistant(): void
    {
        try {
            (new Helper(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_KEY)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_RESOURCE_NAME)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_DEPLOYMENT_ID)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_MODEL)),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            ))->aiAssistantChat($this->getUser(), $this->request->getBody()->getContents());
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
