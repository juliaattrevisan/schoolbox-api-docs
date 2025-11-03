<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Ramsey\Uuid\Uuid;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\OpenAi\Agent\CkEditorTextChat;
use Schoolbox\OpenAi\Agent\ImageGeneration;
use Schoolbox\OpenAi\Agent\LessonPlanGeneration;
use Schoolbox\OpenAi\ClientFactory;

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
     * @throws JsonException
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
            /** gen a sessionID guid for keeping track of what is happening
             * for lessonplan each ai request is a new session
             */
            $aiSessionId = (string)Uuid::uuid4();

            $lessonPlanAgent = new LessonPlanGeneration(
                $this->clientFactory->buildForFeature(Helper::FEATURE_LESSONPLAN),
                $lpConfig,
                trim((string)$this->getConfigLoader()->get('spellcheck_language')),
                $this->curriculumNodeMapper,
            );

            try {
                [$response, $status] = $lessonPlanAgent->request(
                    $this->user,
                    $this->requestPost->get('templateTitle'),
                    $this->requestPost->get('lessonPlanTitle'),
                    $this->requestPost->get('context'),
                    $this->requestPost->get('fields'),
                    $this->requestPost->get('duration'),
                    $this->requestPost->get('curriculumNodeIds'),
                    $this->requestPost->get('additionalContext') ?? '',
                    $this->requestPost->get('generateForField') ?? null,
                    $aiSessionId,
                );

                $this->response = $this->responseAsJson($response)->withStatus($status);
            } catch (SchoolboxException $e) {
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

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
     * Generates an image as part of a conversation
     *
     * @return void
     * @throws ClientExceptionInterface
     * @throws JsonException
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

            $imageGenerationConfig = new Config()->decode(
                (string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_IMAGE_GENERATION)
            );
            $agent = new ImageGeneration(
                $this->clientFactory->buildForFeature(Helper::FEATURE_IMAGE),
                $imageGenerationConfig,
            );
            try {
                // Size and style need to be converted
                $response = $agent->requestStream(
                    $this->getUser(),
                    $this->requestPost->get('userPrompt'),
                    $sessionId,
                    size: ($this->requestPost->get('attributes') ?? [])['size'] ?? null,
                    style: ($this->requestPost->get('attributes') ?? [])['style'] ?? null,
                    imageUrl: $this->requestPost->get('imageUrl') ?? null,
                    previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
                );

                // The iterable response can just be passed straight to
                // responseFromStream
                $this->response = $this->responseAsEventStream($response);
                return;
            } catch (SchoolboxException $e) {
                // This will always be a stream
                // Unfortunately the exception isn't raised until the response
                // is output, which doesn't happen until after this method
                // returns.
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                $this->response = $this
                    ->responseAsJson($problem)
                    ->withStatus($e->getCode());
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
                ->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }
    }


    /**
     * This is used by ckeditor's Little Robot Dude ai assistant which posts
     * to /openai route via some direct javascript thing
     *
     * @return void
     * @throws JsonException
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

        $agent = new CkEditorTextChat(
            $this->clientFactory->buildForFeature(Helper::FEATURE_CHAT),
            $chatConfig,
        );

        try {
            $response = $agent->requestStream(
                $this->getUser(),
                $this->request->getParsedBody(),
                $aiSessionId
            );
            $this->response = $this->responseAsEventStream($response);
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
}
