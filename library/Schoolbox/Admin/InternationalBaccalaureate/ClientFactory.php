<?php

/**
 * IBIS SOAP client factory
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Uri;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

class ClientFactory
{
    private const string DEFAULT_APP_ID = 'SCHOOLBOX';

    public function __construct(
        private IConfigLoader $config,
        private HttpClient $httpClient,
        private ?MsgIdGenerator $msgIdGenerator = null
    ) {
    }

    public function build(): IClient
    {
        $endpoint = $this->stringConfig(ConfigMapper::NAME_IBIS_ENDPOINT);
        if ($endpoint === '') {
            throw new UnexpectedValueException('IBIS endpoint is not configured.');
        }

        return new Client(
            $this->httpClient,
            new Uri($endpoint),
            new RegisterCandidatesEnvelopeBuilder()
        );
    }

    /**
     * @param array<int, array<string, string|null>> $candidates
     */
    public function buildRegisterCandidatesRequest(array $candidates): RegisterCandidatesRequest
    {
        $secretKey = $this->stringConfig(ConfigMapper::NAME_IBIS_SECRET_KEY);
        if ($secretKey === '') {
            throw new UnexpectedValueException('IBIS secret key is not configured.');
        }

        $schoolCode = $this->stringConfig(ConfigMapper::NAME_IB_SCHOOL_CODE);
        if ($schoolCode === '') {
            throw new UnexpectedValueException('IB school code is not configured.');
        }

        return new RegisterCandidatesRequest(
            $this->stringConfig(ConfigMapper::NAME_IBIS_APP_ID, self::DEFAULT_APP_ID),
            $this->getMsgIdGenerator()->generate($secretKey),
            $schoolCode,
            $candidates
        );
    }

    private function getMsgIdGenerator(): MsgIdGenerator
    {
        if ($this->msgIdGenerator === null) {
            $this->msgIdGenerator = new MsgIdGenerator();
        }

        return $this->msgIdGenerator;
    }

    private function stringConfig(string $name, string $default = ''): string
    {
        $value = $this->config->get($name);
        if ($value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }
}
