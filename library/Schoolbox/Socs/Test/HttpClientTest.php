<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs;

if (!function_exists(__NAMESPACE__ . '\sleep')) {
    function sleep(int $seconds): void
    {
    }
}

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
     * Confirm SOCS HTTP requests add authentication details and parse XML responses.
     *
     * @throws \ReflectionException
     * @throws \JsonException
     */
    public function testGetReturnsParsedXml(): void
    {
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://socs.example.test/fixturecalendar.ashx',
                [
                    'query' => [
                        'ID' => 'school',
                        'key' => 'token',
                        'team' => 'firsts',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], '<fixtures><fixture id="1" /></fixtures>'));

        $client = $this->createHttpClient($guzzle);

        $response = $client->get('/fixturecalendar.ashx', ['team' => 'firsts']);

        $this->assertInstanceOf(\SimpleXMLElement::class, $response);
        $this->assertSame('fixture', $response->fixture->getName());
        $this->assertSame('1', (string)$response->fixture['id']);
    }

    /**
     * Confirm empty SOCS responses are returned as false.
     *
     * @throws \ReflectionException
     * @throws \JsonException
     */
    public function testGetReturnsFalseForEmptyResponse(): void
    {
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], ''));

        $client = $this->createHttpClient($guzzle);

        $this->assertFalse($client->get('fixturecalendar.ashx', []));
    }

    /**
     * Confirm SOCS HTTP requests are retried when Guzzle cannot complete a request.
     *
     * @throws \ReflectionException
     * @throws \JsonException
     */
    public function testGetRetriesGuzzleExceptionsBeforeReturningParsedXml(): void
    {
        $attempts = 0;
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(function () use (&$attempts): Response {
                $attempts++;

                if ($attempts < 3) {
                    throw new ConnectException('SOCS unavailable', new Request('GET', 'fixturecalendar.ashx'));
                }

                return new Response(200, [], '<xml><status>ok</status></xml>');
            });

        $client = $this->createHttpClient($guzzle);

        $response = $client->get('fixturecalendar.ashx', []);

        $this->assertInstanceOf(\SimpleXMLElement::class, $response);
        $this->assertSame('ok', (string)$response->status);
        $this->assertSame(3, $attempts);
    }

    /**
     * Confirm non-200 SOCS responses include useful request context.
     *
     * @throws \ReflectionException
     */
    public function testGetThrowsRuntimeExceptionForBadStatus(): void
    {
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(500, [], 'Server error'));

        $client = $this->createHttpClient($guzzle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage(
            'Bad endpoint fixturecalendar.ashx: 500 Internal Server Error after 0 retries. Payload: {"team":"firsts"}'
        );

        $client->get('fixturecalendar.ashx', ['team' => 'firsts']);
    }

    /**
     * Confirm invalid XML responses fail loudly.
     *
     * @throws \ReflectionException
     */
    public function testGetThrowsRuntimeExceptionForInvalidXml(): void
    {
        $guzzle = $this->getMockBuilder(Client::class)
            ->onlyMethods(['request'])
            ->getMock();
        $guzzle
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], '<xml>'));

        $client = $this->createHttpClient($guzzle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to parse SOCS XML payload; aborting.');

        $client->get('fixturecalendar.ashx', []);
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
