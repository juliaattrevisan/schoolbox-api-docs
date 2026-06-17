<?php

/**
 * IBIS register candidates SOAP envelope builder test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesEnvelopeBuilder;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesRequest;

class RegisterCandidatesEnvelopeBuilderTest extends TestCase
{
    public function testBuildsRegisterCandidatesEnvelopeWithEscapedCandidateValues(): void
    {
        $request = new RegisterCandidatesRequest(
            'SCHOOLBOX',
            str_repeat('A', 96),
            '001700',
            [
                [
                    'date_of_birth' => '21-NOV-1979',
                    'firstname' => 'Test & One',
                    'gender' => 'FEMALE',
                    'language1' => 'ENG',
                    'language2' => '',
                    'lastname' => 'User <One>',
                    'nationality1' => 'GB',
                    'nationality2' => '',
                    'reference_code' => '123457',
                    'email' => 'schoolboxtest1@ibo.org',
                ],
            ]
        );

        $xml = (new RegisterCandidatesEnvelopeBuilder())->build($request);

        $this->assertStringContainsString('soapenv:Envelope', $xml);
        $this->assertStringContainsString('api:ws_register_candidates', $xml);
        $this->assertStringContainsString('<appID>SCHOOLBOX</appID>', $xml);
        $this->assertStringContainsString('<msgID xsi:type="xsd:string">' . str_repeat('A', 96) . '</msgID>', $xml);
        $this->assertStringContainsString('<firstname>Test &amp; One</firstname>', $xml);
        $this->assertStringContainsString('<lastname>User &lt;One&gt;</lastname>', $xml);
        $this->assertStringContainsString('<school>001700</school>', $xml);
        $this->assertStringContainsString('<language2/>', $xml);
        $this->assertStringContainsString('<nationality2/>', $xml);

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
    }
}
