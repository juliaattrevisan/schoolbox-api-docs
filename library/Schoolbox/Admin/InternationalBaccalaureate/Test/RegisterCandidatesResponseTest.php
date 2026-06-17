<?php

/**
 * IBIS register candidates SOAP response test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesResponse;
use UnexpectedValueException;

class RegisterCandidatesResponseTest extends TestCase
{
    public function testParsesSuccessfulSoapBodyAndPreservesRawXml(): void
    {
        $xml = <<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <soapenv:Body>
  <ns1:ws_register_candidatesResponse soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns1="http://api.research">
   <ws_register_candidatesReturn soapenc:arrayType="ns1:soap_response[1]" xsi:type="soapenc:Array" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
    <ws_register_candidatesReturn xsi:type="ns1:candidate_response">
     <candidateId xsi:type="xsd:string">mlb495</candidateId>
     <referenceId xsi:type="xsd:string">123456</referenceId>
     <text xsi:type="xsd:string">Success</text>
    </ws_register_candidatesReturn>
   </ws_register_candidatesReturn>
  </ns1:ws_register_candidatesResponse>
 </soapenv:Body>
</soapenv:Envelope>
XML;

        $response = RegisterCandidatesResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isFault());
        $this->assertSame('ws_register_candidatesResponse', $response->getOperationName());
        $this->assertSame($xml, $response->getRawXml());
        $this->assertSame(
            [
                [
                    'candidateId' => 'mlb495',
                    'referenceId' => '123456',
                    'text' => 'Success',
                    'successful' => true,
                ],
            ],
            $response->getCandidateResponses()
        );
        $this->assertSame([], $response->getMessages());
        $this->assertNull($response->getErrorSummary());
    }

    public function testParsesSoapFault(): void
    {
        $response = RegisterCandidatesResponse::fromXml(<<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <soapenv:Fault>
            <faultcode>soapenv:Client</faultcode>
            <faultstring>Invalid candidate payload.</faultstring>
        </soapenv:Fault>
    </soapenv:Body>
</soapenv:Envelope>
XML);

        $this->assertTrue($response->isFault());
        $this->assertFalse($response->isSuccess());
        $this->assertSame('soapenv:Client', $response->getFaultCode());
        $this->assertSame('Invalid candidate payload.', $response->getFaultString());
    }

    public function testParsesSoapBodyValidationErrorAsFailure(): void
    {
        $response = RegisterCandidatesResponse::fromXml(<<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <soapenv:Body>
  <ns1:ws_register_candidatesResponse soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns1="http://api.research">
   <ws_register_candidatesReturn soapenc:arrayType="ns1:soap_response[1]" xsi:type="soapenc:Array" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
    <ws_register_candidatesReturn xsi:type="ns1:soap_response">
     <text xsi:type="xsd:string">cvc-pattern-valid: Value 'Female' is not facet-valid with respect to pattern 'MALE|FEMALE|X' for type '#AnonType_genderCandidateBean'.</text>
    </ws_register_candidatesReturn>
   </ws_register_candidatesReturn>
  </ns1:ws_register_candidatesResponse>
 </soapenv:Body>
</soapenv:Envelope>
XML);

        $this->assertFalse($response->isSuccess());
        $this->assertFalse($response->isFault());
        $this->assertSame([], $response->getCandidateResponses());
        $this->assertSame(
            [
                "cvc-pattern-valid: Value 'Female' is not facet-valid with respect to pattern 'MALE|FEMALE|X' for type '#AnonType_genderCandidateBean'.",
            ],
            $response->getMessages()
        );
        $this->assertSame(
            "cvc-pattern-valid: Value 'Female' is not facet-valid with respect to pattern 'MALE|FEMALE|X' for type '#AnonType_genderCandidateBean'.",
            $response->getErrorSummary()
        );
    }

    public function testRejectsInvalidXml(): void
    {
        $this->expectException(UnexpectedValueException::class);

        RegisterCandidatesResponse::fromXml('not xml');
    }
}
