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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use Schoolbox\Error\Mapper as ErrorMapper;

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
    private const OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const AZURE_API_ENDPOINT = 'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=2023-12-01-preview';


    /**
     * Controller constructor.
     */
    public function __construct(private Client $client, private Log\Mapper $logMapper)
    {
    }

    public function postForward(): void
    {
        $openAiKey = $this->getConfigLoader()->get('openai_key');

        if (!$openAiKey) {
            $problem        = (new ApiProblem('OpenAI API key is unavailable'))
                ->setStatus(404)
                ->asArray();
            $this->response = $this->responseAsJson($problem)
                                   ->withStatus(404);
            return;
        }

        /**
         * @var $log Log\Model
         */
        $log = $this->logMapper->createModel();
        $log->setUser($this->user);

        try {
            $prompt =  $this->request->getBody()->getContents();

            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type'  => 'application/json'],
            ), $prompt);
            $log->setPrompt($prompt);

            $promise = $this->client->sendAsync($openAiRequest, ['stream' => true]);

            // Handle the response when it arrives
            $promise->then(
                function ($response) use ($log): void {
                    http_response_code(200);
                    $log->setReturnCode(200);
                    $log->setErrorMessage('');

                    //header('X-Accel-Buffering: no');
                    header('Content-Type: application/json');
                    header('Content-Encoding: none');

                    ob_implicit_flush(true);
                    ob_end_flush();

                    $stream = $response->getBody();

                    // Read and proxy the data
                    while (!$stream->eof()) {
                        echo $stream->read(1024);
                        //ob_flush();
                        //flush();
                    }
                },
                function ($reason) use ($log): void {
                    /* @var ClientException $reason */
                    http_response_code($reason->getCode());
                    echo $reason->getMessage();

                    $log->setReturnCode($reason->getCode());
                    $log->setErrorMessage($reason->getMessage());
                }
            );

            $promise->wait();
        } catch (GuzzleException $e) {
            $this->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }

        $this->logMapper->save($log);

        die();
    }

    /**
     * Creates an endpoint URL string for the user selected endpoint.  They can choose between OpenAI and Azure.
     *
     */
    private function getEndpoint(): string
    {
        switch ($this->getConfigLoader()->get('openai_endpoint')) {
            case 'OpenAI':
                return self::OPENAI_API_ENDPOINT;
            case 'Azure':
                if (empty($this->getConfigLoader()->get('openai_azure_resource_name')) || empty($this->getConfigLoader()->get('openai_azure_deployment_id'))) {
                    throw new \UnexpectedValueException('Azure resource name and deployment ID are required');
                }

                return sprintf(
                    self::AZURE_API_ENDPOINT,
                    $this->getConfigLoader()->get('openai_azure_resource_name'),
                    $this->getConfigLoader()->get('openai_azure_deployment_id')
                );
        }
        throw new \UnexpectedValueException('Endpoint must be configured');
    }

    private function getAuthorization(): array
    {
        switch ($this->getConfigLoader()->get('openai_endpoint')) {
            case 'OpenAI':
                return ['Authorization' => 'Bearer ' . $this->getConfigLoader()->get('openai_key')];
            case 'Azure':
                return ['api-key' => $this->getConfigLoader()->get('openai_key')];
        }

        throw new \UnexpectedValueException('Endpoint must be configured');
    }
}
