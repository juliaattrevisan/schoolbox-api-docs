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
use SensitiveParameter;
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
        #[SensitiveParameter] private string $secret,
        private Handler $handler,
        private Proxy $proxy,
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
            $this->sendJsonError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (AlaressDatabaseException | \Exception $e) {
            //Catching external database exception and return useful message in json
            $this->sendJsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @api   POST /paperly/agent/proxy
     * @since 25.0.0
     *
     * @return void
     */
    public function postAgentProxyRequest(): void
    {
        $this->handlePaperlyProxyRequest($this->request->getParsedBody());
    }

    /**
     * Handle paperly SQL queries
     *
     * @param array $data
     * @return void
     */
    private function handlePaperlyProxyRequest(array $data): void
    {
        // Check IP address
        if (!IpUtils::checkIp(Session::getIP(), $this->ipWhitelist)) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        $sql = $data['sql'] ?? null;
        $params = $data['params'] ?? null;

        if ($sql === null) {
            $this->sendJsonError(
                'Payload malformed. Query missing.',
                Response::HTTP_BAD_REQUEST
            );
            return;
        }

        // Checking hash to make sure the sql request matches with hash
        $hash = $data['hash'] ?? null;
        if (!isset($hash) || !$this->proxy->isValidRequest($hash, $sql, $params, $this->secret)) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        if (!is_array($params) && !is_null($params)) {
            $this->sendJsonError(
                'Payload malformed. Params must be of type array.',
                Response::HTTP_BAD_REQUEST
            );
            return;
        }

        try {
            $response = [];
            $shouldReturn = $this->proxy->shouldReturnResults($sql);
            $stmt = $this->proxy->executeQuery($sql, $params);

            if ($shouldReturn) {
                $response = $this->proxy->retrieveResults($stmt);
            } else {
                $response = [
                    'success' => true
                ];
            }
            $this->response = $this->responseAsJson(
                $response,
                true
            );
        } catch (AlaressDatabaseException $e) {
            //Catching external database exception and return useful message in json
            $this->sendJsonError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (OutOfBoundsException $e) {
            $this->sendJsonError($e->getMessage(), Response::HTTP_METHOD_NOT_ALLOWED);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
