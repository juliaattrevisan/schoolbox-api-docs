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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

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
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     * @throws ClientExceptionInterface
     */
    public function postLessonPlan(): void
    {
        try {
            $helper = new Helper(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_KEY)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_RESOURCE_NAME)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_DEPLOYMENT_ID)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_MODEL)),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            );

            $payloadGenerator = (new Payload\LessonPlan(
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_MODEL)),
                $this->curriculumNodeMapper
            ));

            /* Generate a payload for ai, using the prompt pieces for System and User info
             * From a POST payload
             */
            $inputs = [
                'context' => $this->requestPost->get('context'),
                'folder' => $this->requestPost->get('folder'),
                'fields' => $this->requestPost->get('fields'),
                'curriculumNodeIds' => $this->requestPost->get('curriculumNodeIds'),
                'additionalContext' => $this->requestPost->get('additionalContext') ?? ''
            ];

//            $inputs = [
//                'context' => 'Year 8 science',
//                'folder' => 'Snakes',
//                'fields' => [
//                    [
//                        'id' => 189235,
//                        'title' => 'Stimsons Pythons',
//                        'description' => 'Write a paragraph describing the life cycle of the Stimsons Python',
//                        'visibility' => 'student',
//                        'body' => 'bleah',
//                        'selected' => true
//                    ]
//                ],
//                'curriculumNodeIds' => [
//                    7561, 6310, 6299
//                ],
//                'additionalContext' => '',
//            ];

            $payload = $payloadGenerator->generate(
                $inputs['context'],
                $inputs['folder'],
                $inputs['fields'],
                $inputs['curriculumNodeIds'],
                $inputs['additionalContext']
            );

            /* Send it and decode the result (if any: sometimes it returns null)
             * and dodge around all its weird encoding business with a double-decode,
             * and ditching all its training markdown (lol) that it loves to use
             *
             * https://community.openai.com/t/json-returning-with-json/584818/6
             */
            $result = $helper->request($this->getUser(), $payload);

            $data = json_decode($result, true);

            $this->response = $this->responseAsJson(json_decode($data['output'][0]['content'][0]['text']) ?? null);

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
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_ENDPOINT)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_KEY)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_RESOURCE_NAME)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_DEPLOYMENT_ID)),
                trim((string)$this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_MODEL)),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            ))->littleRobotDude($this->getUser(), $this->request->getBody()->getContents());
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
