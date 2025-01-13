<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Web\Controller\Base;
use OutOfBoundsException;
use Session;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the Paperly agent.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param array<string> $ipWhitelist A whitelist of IP addresses or CIDR ranges which may access the agent.
     * @param string        $secret      A shared secret between the agent and this instance.
     * @param Handler       $handler     A handler for the actions the agent may perform.
     */
    public function __construct(
        private array $ipWhitelist,
        private string $secret,
        private Handler $handler,
    ) {
    }

    /**
     * @api GET /paperly/agent
     * @since 24.0
     *
     * @return void
     */
    public function getAgentRequest(): void
    {
        $this->handlePaperlyRequest($this->request->getQueryParams());
    }

    /**
     * @api POST /paperly/agent
     * @since 24.0
     *
     * @return void
     */
    public function postAgentRequest(): void
    {
        $this->handlePaperlyRequest($this->request->getParsedBody());
    }

    /**
     * Handle incoming Paperly request.
     *
     * @param array $data
     * @return void
     */
    private function handlePaperlyRequest(array $data): void
    {
        // Check IP address
        if (!IpUtils::checkIp(Session::getIP(), $this->ipWhitelist)) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        // Check secret
        $secret = $data['v'] ?? null;
        if (!isset($secret) || $this->secret !== $secret) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        try {
            $this->response = $this->responseAsJson($this->handler->handle($data));
        } catch (OutOfBoundsException $e) {
            $this->sendJsonError($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (AlaressDatabaseException | \Exception $e) {
            //Catching external database exception and return useful message in json
            $this->sendJsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
