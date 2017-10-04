<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Config\TestLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\InlineGrader\Kami\MockConnector;
use Schoolbox\Resource\Storage;

class ConnectorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider providerDisabledConnector
     * @param IConfigLoader $configLoader
     */
    public function testDisabled(IConfigLoader $configLoader): void
    {
        $connector = new Connector($configLoader, new Client());
        $this->assertFalse($connector->isEnabled());
    }


    /**
     * @dataProvider providerEnabledConnector
     * @param IConfigLoader $configLoader
     */
    public function testEnabled(IConfigLoader $configLoader): void
    {
        $connector = new Connector($configLoader, new Client());
        $this->assertTrue($connector->isEnabled());
    }


    /**
     * Test for whether Kami is online
     *
     * @dataProvider providerOnlineResponse
     *
     * @param bool $expected
     * @param ResponseInterface $response A mocked response from Kami
     */
    public function testOnline(bool $expected, ResponseInterface $response): void
    {
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $this->assertEquals($expected, $connector->isOnline());
    }


    /**
     * Test for whether Kami is online, if Kami is unreachable.
     * (Obviously, it's not online!)
     */
    public function testOnlineUnreachable(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willThrowException(new ConnectException('Destination unreachable', new Request('GET', 'kami.com')));

        $connector = new MockConnector($mockClient);
        $this->assertFalse($connector->isOnline());
    }


    /**
     * Test uploads against the mock API
     */
    public function testUpload(): void
    {
        $connector = new MockConnector(new Client());
        $document  = $connector->uploadDocument($this->getMockStorage());

        $this->assertEquals('2kmcksmndlkansdlk', $document->getId());
        $this->assertEquals('Blah Document', $document->getName());
        $this->assertEquals('processing', $document->getFileStatus());
        $this->assertNull($document->getFileErrorMessage());
    }


    /**
     * Test listing against the mock API
     */
    public function testListDocuments(): void
    {
        $connector = new MockConnector(new Client());

        /* TODO - Kami mock route is currently broken. I'll write to them:
         * * mock route fails if trailing slash. prod route tolerates trailing slash
         * * mock route returns array of documents, prod route returns object with
         *   single key 'documents', which is array of documents
         */
        $this->assertTrue(true);
//        $documents = $connector->listDocuments();
    }


    /**
     * Test getting a document from the Mock API
     */
    public function testGetDocument(): void
    {
        $connector = new MockConnector(new Client());
        $document  = $connector->getDocument('someRandomDocId');
        $this->assertEquals('2kmcksmndlkansdlk', $document->getId());
        $this->assertEquals('Blah Document', $document->getName());
        $this->assertEquals('done', $document->getFileStatus());
        $this->assertNull($document->getFileErrorMessage());
    }


    /**
     * Test deleting a document from the Mock API
     */
    public function testDeleteDocument(): void
    {
        $connector = new MockConnector(new Client());
        $this->assertTrue($connector->deleteDocument('2kmcksmndlkansdlk'));
    }


    public function providerDisabledConnector(): array
    {
        return [
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => null])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => ''])],
        ];
    }


    public function providerEnabledConnector(): array
    {
        return [
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => 'some invalid val'])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => '123'])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => 'Hisohslfd92382923'])],
        ];
    }


    public function providerOnlineResponse(): array
    {
        return [
            'Online' => [true, new Response(404, [], json_encode(['errors' => ['Record Not Found']]))],
            'Bad key' => [false, new Response(401, [], json_encode(['errors' => ['Authorization Failed']]))],
        ];
    }


    private function getMockStorage()
    {
        $storage = new class() extends Storage\Model
        {

            public function readStream()
            {
                return fopen('data://text/plain,' . 'some random file contents', 'rb');
            }
        };
        $storage->setFilename('filename');
        $storage->setExtension('ext');
        return $storage;
    }
}
