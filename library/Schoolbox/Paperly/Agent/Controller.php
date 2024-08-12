<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Symfony\Component\BrowserKit\Request;
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
     * @param string     $ipWhitelist A comma-separated whitelist of IP addresses which may access the agent.
     * @param string     $secret      A shared secret between the agent and this instance.
     * @param IConnector $externalDb  A connection to whatever external database the agent uses:
     *                                currently only Synergetic is supported.
     */
    public function __construct(
        private string $ipWhitelist,
        private string $secret,
        private IConnector $externalDb,
    ) {
    }

    /**
     * @api POST /paperly/agent
     * @since 24.0
     *
     * @return void
     */
    public function postAgentRequest(): void
    {
        // Check IP address
        if (!IpUtils::checkIp($this->getSession()->get('ip'), $this->ipWhitelist)) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
        }

        $data   = $this->request->getParsedBody();
        $secret = $data['v'] ?? null;
        if (!isset($secret) && $this->secret !== $secret) {
            $this->sendJsonError('Access denied', Response::HTTP_UNAUTHORIZED);
            return;
        }

        $method = $data['m'] ?? null;
        if (!isset($method)) {
            $this->sendJsonError('Method does not exist', Response::HTTP_NOT_FOUND);
        }

        switch ($method) {
            case 'allYearLevels':
                $this->handleAllYearLevelsRequest($data);
                break;
            case 'allClasses':
                $this->handleAllClassesRequest($data);
                break;
            case 'allStaffs':
                $this->handleAllStaffsRequest($data);
                break;
            case 'allStudents':
                $this->handleAllStudentsRequest($data);
                break;
            case 'getStudentsAvatar':
                $this->handleGetStudentsAvatarRequest($data);
                break;
            case 'getStudentsMedical':
                $this->handleGetStudentsMedicalRequest($data);
                break;
            case 'getStudentsFromParent':
                $this->handleGetStudentsFromParentRequest($data);
                break;
            case 'getParent':
                $this->handleGetParentRequest($data);
                break;
            case 'healthcheck':
                $this->handleHealthCheckRequest();
                break;
            default:
                $this->sendJsonError('Method does not exist', Response::HTTP_NOT_FOUND);
        }
    }
}
