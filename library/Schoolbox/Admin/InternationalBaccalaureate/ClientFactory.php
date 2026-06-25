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

/**
 * Creates configured IBIS clients and request value objects.
 *
 * The factory centralises config validation so callers fail before building
 * SOAP payloads when required IBIS settings are missing.
 */
class ClientFactory
{
    private const string DEFAULT_APP_ID = 'SCHOOLBOX';

    public function __construct(
        private IConfigLoader $config,
        private HttpClient $httpClient,
        private ?MsgIdGenerator $msgIdGenerator = null
    ) {
    }

    /**
     * Build an IBIS client from the configured endpoint.
     * @return IClient
     */
    public function build(): IClient
    {
        $endpoint = $this->stringConfig(ConfigMapper::NAME_IBIS_ENDPOINT);
        if ($endpoint === '') {
            throw new UnexpectedValueException('IBIS endpoint is not configured.');
        }

        return new Client(
            $this->httpClient,
            new Uri($endpoint),
            new RegisterCandidatesEnvelopeBuilder(),
            new RegisterSessionsEnvelopeBuilder()
        );
    }

    /**
     * Create a register-candidates request with app ID, school code, and msgID.
     *
     * @param array<int, array<string, string|null>> $candidates
     * @return RegisterCandidatesRequest
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

    /**
     * Create a register-sessions request with app ID and msgID.
     *
     * @param array<int, array<string, mixed>> $sessions
     * @return RegisterSessionsRequest
     */
    public function buildRegisterSessionsRequest(array $sessions): RegisterSessionsRequest
    {
        $secretKey = $this->stringConfig(ConfigMapper::NAME_IBIS_SECRET_KEY);
        if ($secretKey === '') {
            throw new UnexpectedValueException('IBIS secret key is not configured.');
        }

        return new RegisterSessionsRequest(
            $this->stringConfig(ConfigMapper::NAME_IBIS_APP_ID, self::DEFAULT_APP_ID),
            $this->getMsgIdGenerator()->generate($secretKey),
            $sessions
        );
    }

    /**
     * Return the IB school code required by session registration payloads.
     * @return string
     */
    public function getSchoolCode(): string
    {
        $schoolCode = $this->stringConfig(ConfigMapper::NAME_IB_SCHOOL_CODE);
        if ($schoolCode === '') {
            throw new UnexpectedValueException('IB school code is not configured.');
        }

        return $schoolCode;
    }

    /**
     * Lazily create the msgID generator so tests can inject a deterministic one.
     * @return MsgIdGenerator
     */
    private function getMsgIdGenerator(): MsgIdGenerator
    {
        if ($this->msgIdGenerator === null) {
            $this->msgIdGenerator = new MsgIdGenerator();
        }

        return $this->msgIdGenerator;
    }

    /**
     * Read config values as strings while treating null/empty values as absent.
     * @param string $name
     * @param string $default
     * @return string
     */
    private function stringConfig(string $name, string $default = ''): string
    {
        $value = $this->config->get($name);
        if ($value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }
}
