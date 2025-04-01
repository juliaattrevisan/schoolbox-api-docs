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
        private Log\Mapper $logMapper
    ) {
    }

    public function getForward(): void
    {
        $prompt = "Tell me something about Schoolbox";

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
            ))->async($this->getUser(), $prompt);
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
