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

    /**
     * Controller constructor.
     */
    public function __construct(private Client $client)
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

        try {
            $openAiRequest = new Request('POST', self::OPENAI_API_ENDPOINT, [
                'Authorization' => 'Bearer ' . $openAiKey,
                'Content-Type'  => 'application/json',
            ], $this->request->getBody()->getContents());

            $promise = $this->client->sendAsync($openAiRequest, ['stream' => true]);


            // Handle the response when it arrives
            $promise->then(
                function ($response) {
                    http_response_code(200);
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
                function ($reason) {
                    // Handle error if the request fails
                    $this->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
                }
            );

            $promise->wait();

        } catch (GuzzleException $e) {
            $this->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }
        die();
    }
}
