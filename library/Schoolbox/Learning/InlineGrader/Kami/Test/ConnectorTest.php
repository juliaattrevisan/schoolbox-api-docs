<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Test;

use Alaress_Date_Format;
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
     * Test for whether Kami is online, if it's disabled.
     * (Obviously, it's not online!)
     *
     * @dataProvider providerDisabledConnector
     *
     * @param IConfigLoader $configLoader
     */
    public function testOnlineDisabled(IConfigLoader $configLoader): void
    {
        $connector = new Connector($configLoader, new Client());
        $this->assertFalse($connector->isOnline());
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
        $this->markTestIncomplete('Document list is currently broken');
//        $documents = $connector->listDocuments();
    }


    /**
     * Test getting a document from the Mock API
     *
     * @dataProvider providerGetDocument
     *
     * @param string              $name
     * @param string              $documentIdentifier
     * @param string              $fileStatus
     * @param null|string         $fileErrorMessage
     * @param Alaress_Date_Format $createdAt
     * @param ResponseInterface   $response
     */
    public function testGetDocument(
        string $name,
        string $documentIdentifier,
        string $fileStatus,
        ?string $fileErrorMessage,
        Alaress_Date_Format $createdAt,
        ResponseInterface $response
    ): void {
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $document  = $connector->getDocument($documentIdentifier);
        $this->assertEquals($documentIdentifier, $document->getId());
        $this->assertEquals($name, $document->getName());
        $this->assertEquals($fileStatus, $document->getFileStatus());
        $this->assertEquals($fileErrorMessage, $document->getFileErrorMessage());
        $this->assertEquals(
            $createdAt->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
            $document->getCreatedAt()->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC)
        );
    }


    /**
     * Test not finding a document from the Mock API.
     *
     * @expectedException \Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound
     */
    public function testGetDocumentNotFound(): void
    {
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $connector->getDocument('asdfghjkl');
    }


    /**
     * Test getting a document from the Mock API, if its status is bad.
     *
     * @expectedException \Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException
     */
    public function testGetDocumentInvalidStatus(): void
    {
        $response = new Response(
            200,
            [],
            json_encode([
                "name"                => "document.docx",
                "document_identifier" => "asdfghjkl",
                "file_status"         => "unknown",
                "file_error_message"  => 'Bad Status',
                "created_at"          => "2015-09-22T23:22:12.798Z",
            ])
        );
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $connector->getDocument('asdfghjkl');
    }


    /**
     * Test getting a document from the Mock API, if the response code is bad.
     *
     * @expectedException \Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException
     */
    public function testGetDocumentBadResponseCode(): void
    {
        $response = new Response(
            400,
            [],
            json_encode([
                "name"                => "Blah Document",
                "document_identifier" => "2kmcksmndlkansdlk",
                "file_status"         => "done",
                "file_error_message"  => null,
                "created_at"          => "2015-09-22T23:22:12.798Z",
            ])
        );
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $connector->getDocument('2kmcksmndlkansdlk');
    }


    /**
     * Test getting a document from the Mock API, if the response body is bad.
     *
     * @expectedException \Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException
     */
    public function testGetDocumentBadResponseBody(): void
    {
        $response = new Response(200, [], 'LOL');
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);

        $connector = new MockConnector($mockClient);
        $connector->getDocument('2kmcksmndlkansdlk');
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


    public function providerGetDocument(): array
    {
        return [
            // An ordinary document
            'Done' => [
                'name'               => 'Blah Document',
                'documentIdentifier' => '2kmcksmndlkansdlk',
                'fileStatus'         => 'done',
                'fileErrorMessage'   => null,
                'createdAt'          => new Alaress_Date_Format(
                    '2015-09-22T23:22:12.798Z',
                    Alaress_Date_Format::FORMAT_DATETIME_UTC
                ),
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_status"         => "done",
                        "file_error_message"  => null,
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
            // Document still processing
            'Processing' => [
                'name'               => 'image.jpg',
                'documentIdentifier' => '1234567890',
                'fileStatus'         => 'processing',
                'fileErrorMessage'   => null,
                'createdAt'          => new Alaress_Date_Format(
                    '2017-12-31T23:59:59.999Z',
                    Alaress_Date_Format::FORMAT_DATETIME_UTC
                ),
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        'name'                => 'image.jpg',
                        "document_identifier" => "1234567890",
                        "file_status"         => 'processing',
                        "file_error_message"  => null,
                        "created_at"          => "2017-12-31T23:59:59.999Z",
                    ])
                ),
            ],
            // An erroneous document - still retrieved
            'Error' => [
                'name'               => 'video.mov',
                'documentIdentifier' => 'qwertyuiop',
                'fileStatus'         => 'error',
                'fileErrorMessage'   => 'Bad Mimetype',
                'createdAt'          => new Alaress_Date_Format(
                    '2015-09-22T23:22:12.798Z',
                    Alaress_Date_Format::FORMAT_DATETIME_UTC
                ),
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        "name"                => "video.mov",
                        "document_identifier" => "qwertyuiop",
                        "file_status"         => "error",
                        "file_error_message"  => 'Bad Mimetype',
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
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
