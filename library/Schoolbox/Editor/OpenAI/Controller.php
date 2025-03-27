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

        $ai = new Helper($this->client, $this->logMapper, $this->getConfigLoader());

        $ai->async($this->getUser(), payload: $this->request->getBody()->getContents());
    }
}
