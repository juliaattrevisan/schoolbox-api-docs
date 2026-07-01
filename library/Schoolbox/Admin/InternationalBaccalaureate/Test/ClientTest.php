<?php

/**
 * IBIS SOAP client test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Schoolbox\Admin\InternationalBaccalaureate\Client;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesEnvelopeBuilder;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesRequest;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class ClientTest extends TestCase
{
    public function testRegisterCandidatesPostsSoapEnvelopeToIbisEndpoint(): void
    {
        $sentRequest = null;
        $sentOptions = null;
        $httpClient = new HttpClient([
            'handler' => function (RequestInterface $request, array $options) use (&$sentRequest, &$sentOptions) {
                $sentRequest = $request;
                $sentOptions = $options;

                return new Response(200, [], $this->successResponseXml());
            },
        ]);

        $response = (new Client(
            $httpClient,
            new Uri('https://ibis-test-api.ibo.org'),
            new RegisterCandidatesEnvelopeBuilder()
        ))->registerCandidates($this->request());

        $this->assertTrue($response->isSuccess());
        $this->assertInstanceOf(RequestInterface::class, $sentRequest);
        $this->assertSame('POST', $sentRequest->getMethod());
        $this->assertSame(
            'https://ibis-test-api.ibo.org/research/api/candidate_registration.cfc',
            (string)$sentRequest->getUri()
        );
        $this->assertSame('text/xml; charset=utf-8', $sentRequest->getHeaderLine('Content-Type'));
        $this->assertSame('""', $sentRequest->getHeaderLine('SOAPAction'));
        $this->assertStringContainsString('<api:ws_register_candidates>', (string)$sentRequest->getBody());
        $this->assertFalse($sentOptions[RequestOptions::ALLOW_REDIRECTS]);
        $this->assertSame(10, $sentOptions[RequestOptions::CONNECT_TIMEOUT]);
        $this->assertSame(30, $sentOptions[RequestOptions::TIMEOUT]);
    }

    public function testRegisterCandidatesConvertsTransportFailuresToSchoolboxExceptions(): void
    {
        $secretKey = uniqid();
        $httpClient = new HttpClient([
            'handler' => fn(RequestInterface $request) => throw new ConnectException(
                'Could not connect with key ' . $secretKey,
                new Request('POST', 'https://ibis-test-api.ibo.org')
            ),
        ]);

        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage('IBIS candidate registration request failed.');

        try {
            (new Client(
                $httpClient,
                new Uri('https://ibis-test-api.ibo.org'),
                new RegisterCandidatesEnvelopeBuilder()
            ))->registerCandidates($this->request());
        } catch (SchoolboxException $e) {
            $this->assertStringNotContainsString($secretKey, $e->getMessage());
            throw $e;
        }
    }

    public function testRegisterCandidatesConvertsHttpFailuresToSchoolboxExceptions(): void
    {
        $httpClient = new HttpClient([
            'handler' => fn() => new Response(500, [], 'server failure'),
        ]);

        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage('IBIS candidate registration failed with HTTP status 500.');

        (new Client(
            $httpClient,
            new Uri('https://ibis-test-api.ibo.org'),
            new RegisterCandidatesEnvelopeBuilder()
        ))->registerCandidates($this->request());
    }

    public function testRegisterCandidatesConvertsInvalidXmlToSchoolboxExceptions(): void
    {
        $httpClient = new HttpClient([
            'handler' => fn() => new Response(200, [], 'not xml'),
        ]);

        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage('IBIS candidate registration returned invalid XML.');

        (new Client(
            $httpClient,
            new Uri('https://ibis-test-api.ibo.org'),
            new RegisterCandidatesEnvelopeBuilder()
        ))->registerCandidates($this->request());
    }

    public function testRegisterCandidatesConvertsSoapFaultsToSchoolboxExceptions(): void
    {
        $httpClient = new HttpClient([
            'handler' => fn() => new Response(200, [], $this->faultResponseXml()),
        ]);

        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage('IBIS SOAP fault: Invalid candidate payload.');

        (new Client(
            $httpClient,
            new Uri('https://ibis-test-api.ibo.org'),
            new RegisterCandidatesEnvelopeBuilder()
        ))->registerCandidates($this->request());
    }

    private function request(): RegisterCandidatesRequest
    {
        return new RegisterCandidatesRequest(
            'SCHOOLBOX',
            str_repeat('A', 96),
            str_pad((string)random_int(1000, 9999), 6, '0', STR_PAD_LEFT),
            [
                [
                    'date_of_birth' => '21-NOV-1979',
                    'firstname' => 'Test1',
                    'gender' => 'FEMALE',
                    'language1' => 'ENG',
                    'language2' => '',
                    'lastname' => 'User1',
                    'nationality1' => 'GB',
                    'nationality2' => '',
                    'reference_code' => '123457',
                    'email' => 'schoolboxtest1@ibo.org',
                ],
            ]
        );
    }

    private function successResponseXml(): string
    {
        return <<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <soapenv:Body>
  <ns1:ws_register_candidatesResponse soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns1="http://api.research">
   <ws_register_candidatesReturn soapenc:arrayType="ns1:soap_response[1]" xsi:type="soapenc:Array" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
    <ws_register_candidatesReturn xsi:type="ns1:candidate_response">
     <candidateId xsi:type="xsd:string">mlb495</candidateId>
     <referenceId xsi:type="xsd:string">123457</referenceId>
     <text xsi:type="xsd:string">Success</text>
    </ws_register_candidatesReturn>
   </ws_register_candidatesReturn>
  </ns1:ws_register_candidatesResponse>
 </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function faultResponseXml(): string
    {
        return <<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <soapenv:Fault>
            <faultcode>soapenv:Client</faultcode>
            <faultstring>Invalid candidate payload.</faultstring>
        </soapenv:Fault>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}
