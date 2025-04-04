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
use Schoolbox\Editor\OpenAI\Prompt\LessonPlan;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper\Exception;

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
     * @throws \JsonException
     * @throws ClientExceptionInterface
     */
    public function postLessonPlan(): void
    {
        try {
            $helper = new Helper(
                trim((string)$this->getConfigLoader()->get('openai_endpoint')),
                trim((string)$this->getConfigLoader()->get('openai_key')),
                trim((string)$this->getConfigLoader()->get('openai_azure_resource_name')),
                trim((string)$this->getConfigLoader()->get('openai_azure_deployment_id')),
                trim((string)$this->getConfigLoader()->get('openai_model')),
                $this->client,
                $this->errorMapper,
                $this->logMapper,
            );

            $promptGenerator = new LessonPlan(
                $this->lessonPlanFieldTemplateFieldMapper,
                $this->curriculumNodeMapper
            );

            /* FIXME
             *  Obtain results for each field...ideally this wouldn't be necessary
             *  and that you could get the resultant data struct back for the whole
             *  plan grouped by fields, but I haven't seen the AI capable of doing
             *  that yet. For now, just bash past the barrier: improve it later
             */
            /* Used for an existing lesson plan, typically, via the Modify screen
             */
            /** @var \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan */
            $lessonPlan = $this->mapperProxy->findById(1580);
            $lessonPlanTemplate = $this->lessonPlanTemplateMapper->findById($lessonPlan->getLessonPlanTemplate());

            $prompt = $promptGenerator->generateFromInstances(
                $lessonPlan,
                $lessonPlanTemplate->getFields(),
            );

//            /* From a POST payload
//             */
//            $prompt = $promptGenerator->generateFromScalars(
//                '9 English',
//                '9A English',
//                [104, 105, 106, 107],
//                [772, 773]
//            );

            $responseFormat = [
                'name' => 'lessonPlanFieldsResponseFormat',
                'type' => 'json_schema',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                        ],
                        'title' => [
                            'type' => 'string',
                        ],
                        'body' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['id', 'title', 'body']
                ],
            ];

            $result = $helper->request($this->getUser(), $prompt, responseFormat: $responseFormat);

            $data = json_decode($result, true);

            new \Alaress_Probe($data);die();
            foreach ($data['choices'] as $choice) {
                echo $choice['message']['content'];
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
                trim((string)$this->getConfigLoader()->get('openai_endpoint')),
                trim((string)$this->getConfigLoader()->get('openai_key')),
                trim((string)$this->getConfigLoader()->get('openai_azure_resource_name')),
                trim((string)$this->getConfigLoader()->get('openai_azure_deployment_id')),
                trim((string)$this->getConfigLoader()->get('openai_model')),
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
