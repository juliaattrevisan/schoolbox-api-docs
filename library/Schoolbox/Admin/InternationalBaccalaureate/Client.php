<?php

/**
 * IBIS SOAP client
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use UnexpectedValueException;

class Client implements IClient
{
    private const string ENDPOINT_REGISTER_CANDIDATES = '/research/api/candidate_registration.cfc';
    private const int CONNECT_TIMEOUT = 10;
    private const int TIMEOUT = 30;

    public function __construct(
        private HttpClient $httpClient,
        private UriInterface $baseUrl,
        private RegisterCandidatesEnvelopeBuilder $candidateEnvelopeBuilder,
        private ?RegisterSessionsEnvelopeBuilder $sessionEnvelopeBuilder = null
    ) {
    }

    public function registerCandidates(RegisterCandidatesRequest $request): RegisterCandidatesResponse
    {
        $httpRequest = new Request(
            'POST',
            $this->generateUrl(),
            [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '""',
            ],
            $this->candidateEnvelopeBuilder->build($request)
        );

        try {
            $httpResponse = $this->httpClient->send($httpRequest, [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
                RequestOptions::TIMEOUT => self::TIMEOUT,
            ]);
        } catch (GuzzleException $e) {
            throw new SchoolboxException(
                'IBIS candidate registration request failed.',
                0,
                $e,
                502
            );
        }

        $statusCode = $httpResponse->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new SchoolboxException(
                sprintf('IBIS candidate registration failed with HTTP status %d.', $statusCode),
                0,
                null,
                502
            );
        }

        try {
            $response = RegisterCandidatesResponse::fromXml((string)$httpResponse->getBody());
        } catch (UnexpectedValueException $e) {
            throw new SchoolboxException(
                'IBIS candidate registration returned invalid XML.',
                0,
                $e,
                502
            );
        }

        if ($response->isFault()) {
            throw new SchoolboxException(
                'IBIS SOAP fault: ' . ($response->getFaultString() ?? 'Unknown SOAP fault.'),
                0,
                null,
                502
            );
        }

        return $response;
    }

    public function registerSessions(RegisterSessionsRequest $request): RegisterSessionsResponse
    {
        $httpRequest = new Request(
            'POST',
            $this->generateUrl(),
            [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '""',
            ],
            $this->getSessionEnvelopeBuilder()->build($request)
        );

        try {
            $httpResponse = $this->httpClient->send($httpRequest, [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
                RequestOptions::TIMEOUT => self::TIMEOUT,
            ]);
        } catch (GuzzleException $e) {
            throw new SchoolboxException(
                'IBIS session registration request failed.',
                0,
                $e,
                502
            );
        }

        $statusCode = $httpResponse->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new SchoolboxException(
                sprintf('IBIS session registration failed with HTTP status %d.', $statusCode),
                0,
                null,
                502
            );
        }

        try {
            $response = RegisterSessionsResponse::fromXml((string)$httpResponse->getBody());
        } catch (UnexpectedValueException $e) {
            throw new SchoolboxException(
                'IBIS session registration returned invalid XML.',
                0,
                $e,
                502
            );
        }

        if ($response->isFault()) {
            throw new SchoolboxException(
                'IBIS SOAP fault: ' . ($response->getFaultString() ?? 'Unknown SOAP fault.'),
                0,
                null,
                502
            );
        }

        return $response;
    }

    private function generateUrl(): string
    {
        return (string)$this->baseUrl->withPath(
            rtrim($this->baseUrl->getPath(), '/') . self::ENDPOINT_REGISTER_CANDIDATES
        );
    }

    private function getSessionEnvelopeBuilder(): RegisterSessionsEnvelopeBuilder
    {
        if ($this->sessionEnvelopeBuilder === null) {
            $this->sessionEnvelopeBuilder = new RegisterSessionsEnvelopeBuilder();
        }

        return $this->sessionEnvelopeBuilder;
    }
}
