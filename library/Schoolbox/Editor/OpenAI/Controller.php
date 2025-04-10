<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use DebugBar\DebugBarException;
use GuzzleHttp\Client;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
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
        private AssessmentMapperProxy $mapperProxy,
        private \Schoolbox\Admin\LessonPlan\Template\Mapper $lessonPlanTemplateMapper,
        private \Schoolbox\Admin\LessonPlan\Template\Field\Mapper $lessonPlanFieldTemplateFieldMapper,
    ) {
    }


    /**
     * Generates a payload about lesson plans
     *
     * @return void
     * @throws Exception
     * @throws Deleted
     * @throws DebugBarException
     * @throws \JsonException
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
                $this->lessonPlanFieldTemplateFieldMapper,
                $this->curriculumNodeMapper
            ));

            if ($this->requestPost['lessonPlanId']) {
                /* Generate a payload for ai, using the prompt pieces for System and User info
                 * Used for an existing lesson plan, typically, via the Modify screen
                 */
                /** @var \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan */
                $lessonPlan = $this->mapperProxy->findById($this->requestPost['lessonPlanId']);
                $lessonPlanTemplate = $this->lessonPlanTemplateMapper->findById($lessonPlan->getLessonPlanTemplate());

                $payload = $payloadGenerator->generateFromInstances(
                    $lessonPlan,
                    $lessonPlanTemplate->getFields(),
                    $this->requestPost->get('additionalContext') ?? ''
                );
            } else {
                /* Generate a payload for ai, using the prompt pieces for System and User info
                 * From a POST payload
                 */
                $payload = $payloadGenerator->generateFromScalars(
                    $this->requestPost->get('context'),
                    $this->requestPost->get('folder'),
                    explode(',', $this->requestPost->get('lessonPlanTemplateFieldIds')),
                    explode(',', $this->requestPost->get('curriculumNodeIds')),
                    $this->requestPost->get('additionalContext') ?? ''
                );
            }

            /* Send it and decode the result (if any: sometimes it returns null)
             * and dodge around all its weird encoding business with a double-decode,
             * and ditching all its training markdown (lol) that it loves to use
             *
             * https://community.openai.com/t/json-returning-with-json/584818/6
             */
            $result = $helper->request($this->getUser(), $payload);

            $data = json_decode($result, true);

            $result = preg_replace('/\\n(\s)*/', '', $data['choices'][0]['message']['content']);

            $this->response = $this->responseAsJson(json_decode($result) ?? null);

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
