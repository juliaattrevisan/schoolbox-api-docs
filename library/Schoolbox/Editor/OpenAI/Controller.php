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
        /** @var \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan */
        $lessonPlan = $this->mapperProxy->findById(1580);

        $lessonPlanTemplate = $this->lessonPlanTemplateMapper->findById($lessonPlan->getLessonPlanTemplate());

        $post = [
            'title' => 'Digital Literacy',
            /* 45 minute example lesson
             */
            'duration' => 45,
            'folderTitle' => 'English',
            'unitTitle' => 'Unit 1',
            'yearLevel' => 'Year 9',
            'curriculumIds' => [
                253,
                256,
                259
            ]
        ];

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

            $promptGenerator = new LessonPlan($this->curriculumNodeMapper);

            /* FIXME
             *  Obtain results for each field...ideally this wouldn't be necessary
             *  and that you could get the resultant data struct back for the whole
             *  plan grouped by fields, but I haven't seen the AI capable of doing
             *  that yet. For now, just bash past the barrier: improve it later
             */
            foreach ($lessonPlanTemplate->getFields() as $lessonPlanTemplateField) {
                $prompt = $promptGenerator->generateFromInstances(
                    $lessonPlan,
                    $lessonPlanTemplateField,
                );

                $result = $helper->request($this->getUser(), $prompt);

                $data = json_decode($result, true);

                foreach ($data['choices'] as $choice) {
                    echo $choice['message']['content'];
                }
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
