<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Schoolbox\Socs\HttpClient;

#[\PHPUnit\Framework\Attributes\CoversClass(HttpClient::class)]
class HttpClientTest extends TestCase
{
    /**
     * Confirm SOCS HTTP requests are retried when Guzzle cannot complete a request.
     *
     * @throws \ReflectionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testGetRetriesGuzzleExceptionsBeforeReturningResponse(): void
    {
        $attempts = 0;
        $expectedResponse = new Response(200, [], '<xml />');
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(function () use (&$attempts, $expectedResponse): Response {
                $attempts++;

                if ($attempts < 3) {
                    throw new ConnectException('SOCS unavailable', new Request('GET', 'fixturecalendar.ashx'));
                }

                return $expectedResponse;
            });

        $client = $this->createHttpClient($guzzle);

        $response = $client->get('fixturecalendar.ashx', []);

        $this->assertSame($expectedResponse, $response);
        $this->assertSame(3, $attempts);
    }

    /**
     * Confirm SOCS HTTP requests throw the final Guzzle exception once retries are exhausted.
     *
     * @throws \ReflectionException
     */
    public function testGetThrowsGuzzleExceptionAfterRetryLimit(): void
    {
        $attempts = 0;
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(function () use (&$attempts): never {
                $attempts++;

                throw new ConnectException('SOCS unavailable', new Request('GET', 'fixturecalendar.ashx'));
            });

        $client = $this->createHttpClient($guzzle);

        $this->expectException(ConnectException::class);
        $this->expectExceptionMessage('SOCS unavailable');

        try {
            $client->get('fixturecalendar.ashx', []);
        } finally {
            $this->assertSame(3, $attempts);
        }
    }

    /**
     * Create a SOCS HTTP client with a mocked Guzzle client.
     *
     * @param Client $guzzle
     * @return HttpClient
     * @throws \ReflectionException
     */
    private function createHttpClient(Client $guzzle): HttpClient
    {
        $client = new HttpClient('https://socs.example.test', 'school', 'token');
        $clientProperty = new \ReflectionProperty($client, 'client');
        $clientProperty->setValue($client, $guzzle);

        return $client;
    }
}
