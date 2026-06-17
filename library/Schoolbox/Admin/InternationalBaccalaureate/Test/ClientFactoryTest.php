<?php

/**
 * IBIS SOAP client factory test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Schoolbox\Admin\InternationalBaccalaureate\ClientFactory;
use Schoolbox\Admin\InternationalBaccalaureate\MsgIdGenerator;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesRequest;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

class ClientFactoryTest extends TestCase
{
    public function testBuildUsesConfiguredEndpoint(): void
    {
        $config = $this->createStub(IConfigLoader::class);
        $config
            ->method('get')
            ->willReturnMap([
                [ConfigMapper::NAME_IBIS_ENDPOINT, 'https://ibis.example.edu'],
            ]);

        $sentRequest = null;
        $httpClient = new HttpClient([
            'handler' => function (RequestInterface $request) use (&$sentRequest) {
                $sentRequest = $request;

                return new Response(200, [], <<<'XML'
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <ws_register_candidatesResponse>
            <return>true</return>
        </ws_register_candidatesResponse>
    </soapenv:Body>
</soapenv:Envelope>
XML);
            },
        ]);

        (new ClientFactory($config, $httpClient))
            ->build()
            ->registerCandidates($this->request());

        $this->assertInstanceOf(RequestInterface::class, $sentRequest);
        $this->assertSame(
            'https://ibis.example.edu/research/api/candidate_registration.cfc',
            (string)$sentRequest->getUri()
        );
    }

    public function testBuildRequiresConfiguredEndpoint(): void
    {
        $config = $this->createStub(IConfigLoader::class);
        $config
            ->method('get')
            ->willReturnMap([
                [ConfigMapper::NAME_IBIS_ENDPOINT, ''],
            ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('IBIS endpoint is not configured.');

        (new ClientFactory($config, new HttpClient()))->build();
    }

    public function testBuildRegisterCandidatesRequestUsesConfiguredValuesAndGeneratedMsgId(): void
    {
        $config = $this->createStub(IConfigLoader::class);
        $config
            ->method('get')
            ->willReturnMap([
                [ConfigMapper::NAME_IBIS_APP_ID, 'CUSTOMAPP'],
                [ConfigMapper::NAME_IBIS_SECRET_KEY, '31225BE2C7309F8C35D8A521897EFE16'],
                [ConfigMapper::NAME_IB_SCHOOL_CODE, '001700'],
            ]);

        $factory = new ClientFactory(
            $config,
            new HttpClient(),
            new MsgIdGenerator(fn() => '0123456789ABCDEF0123456789ABCDEF')
        );

        $request = $factory->buildRegisterCandidatesRequest([
            [
                'date_of_birth' => '21-NOV-1979',
                'firstname' => 'Test1',
                'gender' => 'FEMALE',
                'language1' => 'ENG',
                'lastname' => 'User1',
                'nationality1' => 'GB',
                'reference_code' => '123457',
                'email' => 'schoolboxtest1@ibo.org',
            ],
        ]);

        $this->assertSame('CUSTOMAPP', $request->getAppId());
        $this->assertSame('001700', $request->getSchoolCode());
        $this->assertSame(96, strlen($request->getMsgId()));
    }

    public function testBuildRegisterCandidatesRequestRequiresSecretKey(): void
    {
        $config = $this->createStub(IConfigLoader::class);
        $config
            ->method('get')
            ->willReturnMap([
                [ConfigMapper::NAME_IBIS_APP_ID, null],
                [ConfigMapper::NAME_IBIS_SECRET_KEY, ''],
                [ConfigMapper::NAME_IB_SCHOOL_CODE, '001700'],
            ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('IBIS secret key is not configured.');

        (new ClientFactory($config, new HttpClient()))->buildRegisterCandidatesRequest([]);
    }

    private function request(): RegisterCandidatesRequest
    {
        return new RegisterCandidatesRequest(
            'SCHOOLBOX',
            str_repeat('A', 96),
            '001700',
            [
                [
                    'date_of_birth' => '21-NOV-1979',
                    'firstname' => 'Test1',
                    'gender' => 'FEMALE',
                    'language1' => 'ENG',
                    'lastname' => 'User1',
                    'nationality1' => 'GB',
                    'reference_code' => '123457',
                    'email' => 'schoolboxtest1@ibo.org',
                ],
            ]
        );
    }
}
