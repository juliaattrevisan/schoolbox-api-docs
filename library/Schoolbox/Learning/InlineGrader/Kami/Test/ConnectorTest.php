<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Test;

use Alaress_Date_Format;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\InlineGrader\Kami\Exception\EmptySiteSchoolCode;
use Schoolbox\Learning\InlineGrader\Kami\MockConnector;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class ConnectorTest extends TestCase
{
    /**
     * @param null|string $siteSchoolCode
     * @throws EmptySiteSchoolCode
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\EmptyApiKeyException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException
     * @throws \PHPUnit\Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidSiteSchoolCode')]
    public function testInvalidSiteSchoolCode(?string $siteSchoolCode): void
    {
        // This method must not hit Kami API
        $connector = new Connector($this->getMockClientWithConnectException(), true, Connector::DEFAULT_BASE_URI, 'TEST_API_KEY', $siteSchoolCode);
        $this->expectException(EmptySiteSchoolCode::class);
        $connector->viewSession('kamiDocId', new User());
    }


    /**
     * @param null|string $apiKey
     * @throws \PHPUnit_Framework_AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDisabledConnector')]
    public function testDisabled(?string $apiKey): void
    {
        // This method must not hit Kami API
        $connector = new Connector($this->getMockClientWithConnectException(), false, Connector::DEFAULT_BASE_URI, $apiKey, 'TESTING');
        $this->assertFalse($connector->isEnabled());
    }


    /**
     * @param null|string $apiKey
     * @throws \PHPUnit_Framework_AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEnabledConnector')]
    public function testEnabled(?string $apiKey): void
    {
        // This method must not hit Kami API
        $connector = new Connector($this->getMockClientWithConnectException(), true, Connector::DEFAULT_BASE_URI, $apiKey, 'TESTING');
        $this->assertTrue($connector->isEnabled());
    }


    /**
     * Test for whether Kami is online
     *
     *
     * @param bool $expected
     * @param ResponseInterface $response A mocked response from Kami
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOnlineResponse')]
    public function testOnline(bool $expected, ResponseInterface $response): void
    {
        $connector = new MockConnector($this->getMockClient($response));
        $this->assertEquals($expected, $connector->isOnline());
    }


    /**
     * Test for whether Kami is online, if it's disabled.
     * (Obviously, it's not online!)
     *
     *
     * @param null|string $apiKey
     * @throws \PHPUnit_Framework_AssertionFailedError
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDisabledConnector')]
    public function testOnlineDisabled(?string $apiKey): void
    {
        // This method must not hit Kami API
        $connector = new Connector($this->getMockClientWithConnectException(), true, Connector::DEFAULT_BASE_URI, $apiKey, 'TESTING');
        $this->assertFalse($connector->isOnline());
    }


    /**
     * Test for whether Kami is online, if Kami is unreachable.
     * (Obviously, it's not online!)
     */
    public function testOnlineUnreachable(): void
    {
        $connector = new MockConnector($this->getMockClientWithConnectException());
        $this->assertFalse($connector->isOnline());
    }


    /**
     * Test uploading a document from the Mock API.
     *
     *
     * @param string              $name
     * @param string              $documentIdentifier
     * @param string              $fileStatus
     * @param null|string         $fileErrorMessage
     * @param Alaress_Date_Format $createdAt
     * @param ResponseInterface   $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUpload')]
    public function testUpload(
        string $name,
        string $documentIdentifier,
        string $fileStatus,
        ?string $fileErrorMessage,
        Alaress_Date_Format $createdAt,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
        $document  = $connector->uploadDocument($this->getStorage(), $this->getMockFilesystemProxy());
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
     * Test not finding a document from the Mock API after I upload it.
     *
     *
     */
    public function testUploadNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->uploadDocument($this->getStorage(), $this->getMockFilesystemProxy());
    }


    /**
     * Test upload of a document from the Mock API, and then do something if its status is bad.
     *
     *
     */
    public function testUploadInvalidStatus(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException::class);
        $response = new Response(
            201,
            [],
            json_encode([
                "name"                => "document.docx",
                "document_identifier" => "asdfghjkl",
                "file_status"         => "unknown",
                "file_error_message"  => 'Bad Status',
                "created_at"          => "2015-09-22T23:22:12.798Z",
            ])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->uploadDocument($this->getStorage(), $this->getMockFilesystemProxy());
    }


    /**
     * Test upload of a document from the Mock API, if the response is bad.
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUploadBadResponse')]
    public function testUploadBadResponse(ResponseInterface $response): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException::class);
        $connector = new MockConnector($this->getMockClient($response));
        $connector->uploadDocument($this->getStorage(), $this->getMockFilesystemProxy());
    }


    /**
     * Test upload of a document from the Mock API, if the response body is bad.
     *
     *
     */
    public function testUploadBadResponseBody(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException::class);
        $response  = new Response(201, [], 'LOL');
        $connector = new MockConnector($this->getMockClient($response));
        $connector->uploadDocument($this->getStorage(), $this->getMockFilesystemProxy());
    }


    /**
     * Test listing against the mock API
     *
     *
     * @param int               $expectedCount
     * @param array             $expectedNames
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerListDocuments')]
    public function testListDocuments(int $expectedCount, array $expectedNames, ResponseInterface $response): void
    {
        $connector = new MockConnector($this->getMockClient($response));
        $documents = $connector->listDocuments();

        $this->assertCount($expectedCount, $documents);
        foreach ($expectedNames as $documentId => $expectedName) {
            $this->assertEquals(
                $expectedName,
                $documents[$documentId]->getName(),
                sprintf(
                    'Document "%s" expected name "%s" does not match actual "%s"',
                    $documentId,
                    $expectedName,
                    $documents[$documentId]->getName()
                )
            );
        }
    }


    /**
     * Test getting a document from the Mock API
     *
     *
     * @param string              $name
     * @param string              $documentIdentifier
     * @param string              $fileStatus
     * @param null|string         $fileErrorMessage
     * @param Alaress_Date_Format $createdAt
     * @param ResponseInterface   $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetDocument')]
    public function testGetDocument(
        string $name,
        string $documentIdentifier,
        string $fileStatus,
        ?string $fileErrorMessage,
        Alaress_Date_Format $createdAt,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
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
     * Test getting a document when Kami's not enabled
     *
     *
     *
     * @param null|string $apiKey
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\EmptyApiKeyException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound
     * @throws \Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDisabledConnector')]
    public function testGetDocumentNotEnabled(?string $apiKey): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\EmptyApiKeyException::class);
        // This method must not hit Kami API
        $connector = new Connector($this->getMockClientWithConnectException(), false, Connector::DEFAULT_BASE_URI, $apiKey, 'TESTING');
        $connector->getDocument('asdfghjkl');
    }


    /**
     * Test not finding a document from the Mock API.
     *
     *
     */
    public function testGetDocumentNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getDocument('asdfghjkl');
    }


    /**
     * Test getting a document from the Mock API, if its status is bad.
     *
     *
     */
    public function testGetDocumentInvalidStatus(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException::class);
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
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getDocument('asdfghjkl');
    }


    /**
     * Test getting a document from the Mock API, if the response is bad.
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetDocumentBadResponse')]
    public function testGetDocumentBadResponse(ResponseInterface $response): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException::class);
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getDocument('2kmcksmndlkansdlk');
    }


    /**
     * Test getting a document from the Mock API, if the response body is bad.
     *
     *
     */
    public function testGetDocumentBadResponseBody(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ResponseDecodeException::class);
        $response  = new Response(200, [], 'LOL');
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getDocument('2kmcksmndlkansdlk');
    }


    /**
     * Test deleting a document from the Mock API
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDeleteDocument')]
    public function testDeleteDocument(ResponseInterface $response): void
    {
        $connector = new MockConnector($this->getMockClient($response));
        $this->assertTrue($connector->deleteDocument('asdfghjkl'));
    }


    /**
     * Test deleting a document that wasn't found
     *
     *
     */
    public function testDeleteDocumentNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->deleteDocument('asdfghjkl');
    }


    /**
     * Test deleting a document from the Mock API, if the response is bad.
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDeleteDocumentBadResponse')]
    public function testDeleteDocumentBadResponse(ResponseInterface $response): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException::class);
        $connector = new MockConnector($this->getMockClient($response));
        $connector->deleteDocument('asdfghjkl');
    }


    /**
     * Test creation of a view session
     *
     *
     * @param string                   $viewerUrl
     * @param string                   $viewSessionKey
     * @param Alaress_Date_Format|null $sessionExpiry
     * @param ResponseInterface        $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerViewSession')]
    public function testViewSession(
        string $viewerUrl,
        string $viewSessionKey,
        ?Alaress_Date_Format $sessionExpiry,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
        $user = new User();
        $user->setFirstname('Mock User');
        $user->setLastname('Mock Surname');

        $session   = $connector->viewSession('2kmcksmndlkansdlk', $user);

        $this->assertEquals($viewerUrl, $session->getViewerUrl());
        $this->assertEquals($viewSessionKey, $session->getViewSessionKey());
        if (isset($sessionExpiry)) {
            $this->assertEquals(
                $sessionExpiry->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC),
                $session->getExpiry()->getUsingFormat(Alaress_Date_Format::FORMAT_DATETIME_UTC)
            );
        } else {
            $this->assertNull($session->getExpiry());
        }
    }


    /**
     * Test getting a view session for a document that wasn't found
     *
     *
     */
    public function testViewSessionNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $user = new User();
        $user->setFirstname('Mock User');
        $user->setLastname('Mock Surname');

        $connector->viewSession('asdfghjkl', $user);
    }


    /**
     * Test bad response from view session request
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerViewSessionBadResponse')]
    public function testViewSessionBadResponse(ResponseInterface $response): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException::class);
        $connector = new MockConnector($this->getMockClient($response));
        $user = new User();
        $user->setFirstname('Mock User');
        $user->setLastname('Mock Surname');

        $connector->viewSession('asdfghjkl', $user);
    }


    /**
     * Test get of an export
     *
     *
     * @param string            $documentIdentifier
     * @param string            $status
     * @param null|string       $fileUrl
     * @param null|string       $errorType
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetExport')]
    public function testGetExport(
        string $documentIdentifier,
        string $status,
        ?string $fileUrl,
        ?string $errorType,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
        $export    = $connector->getExport($documentIdentifier);

        $this->assertEquals($documentIdentifier, $export->getId());
        $this->assertEquals($status, $export->getStatus());
        $this->assertEquals($fileUrl, $export->getFileUrl());
        $this->assertEquals($errorType, $export->getErrorType());
    }


    /**
     * Test create inline export
     *
     *
     * @param string            $documentIdentifier
     * @param string            $status
     * @param null|string       $fileUrl
     * @param null|string       $errorType
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetExport')]
    public function testCreateInlineExport(
        string $documentIdentifier,
        string $status,
        ?string $fileUrl,
        ?string $errorType,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
        $export    = $connector->createInlineExport($documentIdentifier);

        $this->assertEquals($documentIdentifier, $export->getId());
        $this->assertEquals($status, $export->getStatus());
        $this->assertEquals($fileUrl, $export->getFileUrl());
        $this->assertEquals($errorType, $export->getErrorType());
    }


    /**
     * Test creating an export for a document that wasn't found
     *
     *
     */
    public function testCreateInlineExportNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->createInlineExport('asdfghjkl');
    }


    /**
     * Test create annotated export
     *
     *
     * @param string            $documentIdentifier
     * @param string            $status
     * @param null|string       $fileUrl
     * @param null|string       $errorType
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetExport')]
    public function testCreateAnnotatedExport(
        string $documentIdentifier,
        string $status,
        ?string $fileUrl,
        ?string $errorType,
        ResponseInterface $response
    ): void {
        $connector = new MockConnector($this->getMockClient($response));
        $export    = $connector->createAnnotatedExport($documentIdentifier);

        $this->assertEquals($documentIdentifier, $export->getId());
        $this->assertEquals($status, $export->getStatus());
        $this->assertEquals($fileUrl, $export->getFileUrl());
        $this->assertEquals($errorType, $export->getErrorType());
    }


    /**
     * Test creating an export for a document that wasn't found
     *
     *
     */
    public function testCreateAnnotatedExportNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->createAnnotatedExport('asdfghjkl');
    }


    /**
     * Test get export of a document that wasn't found
     *
     *
     */
    public function testGetExportNotFound(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\NotFound::class);
        $response = new Response(
            404,
            [],
            json_encode(["errors" => ["Record Not Found"],])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getExport('asdfghjkl');
    }


    /**
     * Test exporting a document from the Mock API, if the response is bad.
     *
     *
     * @param ResponseInterface $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetExportBadResponse')]
    public function testGetExportBadResponse(ResponseInterface $response): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\ApiException::class);
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getExport('asdfghjkl');
    }


    /**
     * Test exporting a document from the Mock API, if its status is bad.
     *
     *
     */
    public function testGetExportInvalidStatus(): void
    {
        $this->expectException(\Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException::class);
        $response = new Response(
            200,
            [],
            json_encode([
                "id"         => "ccsd",
                "status"     => "invalid",
                "file_url"   => null,
                "error_type" => null,
            ])
        );
        $connector = new MockConnector($this->getMockClient($response));
        $connector->getExport('asdfghjkl');
    }


    public static function providerDisabledConnector(): array
    {
        return [
            [null],
            [''],
        ];
    }


    /**
     * @return string[]|null[]
     */
    public static function providerInvalidSiteSchoolCode(): array
    {
        return [
            [''],
            [' '],
            ["\n"],
            ["\t"],
            [null],
        ];
    }


    public static function providerEnabledConnector(): array
    {
        return [
            ['some invalid val'],
            ['123'],
            ['Hisohslfd92382923'],
        ];
    }


    public static function providerOnlineResponse(): array
    {
        return [
            'Online' => [true, new Response(404, [], json_encode(['errors' => ['Record Not Found']]))],
            'Bad key' => [false, new Response(401, [], json_encode(['errors' => ['Authorization Failed']]))],
        ];
    }


    public function providerGetDocument(): array
    {
        return $this->providerDocument(200);
    }


    public function providerGetDocumentBadResponse(): array
    {
        return $this->providerBadResponse(200);
    }


    public function providerUpload(): array
    {
        return $this->providerDocument(201);
    }


    public function providerUploadBadResponse(): array
    {
        return $this->providerBadResponse(201);
    }


    private function providerDocument(int $statusCode): array
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
                    $statusCode,
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
                    $statusCode,
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
                    $statusCode,
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


    private function providerBadResponse(int $validStatusCode): array
    {
        return [
            'Bad Response Code' => [
                'response' => new Response(
                    400,
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
            'Unknown Error' => [
                'response' => new Response(
                    400,
                    [],
                    json_encode(["errors" => ["Unknown Error"],])
                ),
            ],
            'No Errors' => [
                'response' => new Response(
                    404,
                    [],
                    json_encode(["nothing" => ["Unknown Error"],])
                ),
            ],
            'No Name' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_status"         => "done",
                        "file_error_message"  => null,
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
            'No ID' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "file_status"         => "done",
                        "file_error_message"  => null,
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
            'No Status' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_error_message"  => null,
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
            'No Error' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_status"         => "done",
                        "created_at"          => "2015-09-22T23:22:12.798Z",
                    ])
                ),
            ],
            'No Created' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_status"         => "done",
                        "file_error_message"  => null,
                    ])
                ),
            ],
            'Bad Created Format' => [
                'response' => new Response(
                    $validStatusCode,
                    [],
                    json_encode([
                        "name"                => "Blah Document",
                        "document_identifier" => "2kmcksmndlkansdlk",
                        "file_status"         => "done",
                        "file_error_message"  => null,
                        "created_at"          => "not a date",
                    ])
                ),
            ],
        ];
    }


    public static function providerListDocuments(): array
    {
        return [
            'All good' => [
                'expectedCount' => 3,
                'expectedNames' => [
                    '111' => 'Blah Document 1',
                    '222' => 'Blah Document 2',
                    '333' => 'Blah Document 3',
                ],
                'response' => new Response(
                    200,
                    [],
                    json_encode([
                        'documents' => [
                            [
                                "name"                => "Blah Document 1",
                                "document_identifier" => "111",
                                "file_status"         => "done",
                                "file_error_message"  => null,
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 2",
                                "document_identifier" => "222",
                                "file_status"         => "processing",
                                "file_error_message"  => null,
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 3",
                                "document_identifier" => "333",
                                "file_status"         => "error",
                                "file_error_message"  => 'No error',
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                        ],
                    ])
                ),
            ],
            'Most bad' => [
                'expectedCount' => 1,
                'expectedNames' => [
                    '111' => 'Blah Document 1',
                ],
                'response' => new Response(
                    200,
                    [],
                    json_encode([
                        'documents' => [
                            [
                                "name"                => "Blah Document 1",
                                "document_identifier" => "111",
                                "file_status"         => "done",
                                "file_error_message"  => null,
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 2",
                                "document_identifier" => "222",
                                "file_status"         => "unknown",
                                "file_error_message"  => "bad status",
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 3",
                                "document_identifier" => "333",
                                "file_status"         => "error",
                                "file_error_message"  => 'Bad date',
                                "created_at"          => "2015",
                            ],
                        ],
                    ])
                ),
            ],
            'All bad' => [
                'expectedCount' => 0,
                'expectedNames' => [],
                'response' => new Response(
                    200,
                    [],
                    json_encode([
                        'documents' => [
                            [
                                "name"                => "Blah Document 1",
                                "file_status"         => "done",
                                "file_error_message"  => "Missing ID",
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 2",
                                "document_identifier" => "222",
                                "file_status"         => "unknown",
                                "file_error_message"  => "bad status",
                                "created_at"          => "2015-09-22T23:22:12.798Z",
                            ],
                            [
                                "name"                => "Blah Document 3",
                                "document_identifier" => "333",
                                "file_status"         => "error",
                                "file_error_message"  => 'Bad date',
                                "created_at"          => "2015",
                            ],
                        ],
                    ])
                ),
            ],
        ];
    }


    public static function providerDeleteDocument(): array
    {
        return [
            '200' => [
                'response' => new Response(200),
            ],
            '204' => [
                'response' => new Response(204),
            ],
        ];
    }


    public static function providerDeleteDocumentBadResponse(): array
    {
        return [
            'Bad Response Code' => [
                'response' => new Response(
                    400,
                    [],
                    json_encode(["errors" => ["Nothing Error"],])
                ),
            ],
            'Unknown Error' => [
                'response' => new Response(
                    400,
                    [],
                    json_encode(["errors" => ["Unknown Error"],])
                ),
            ],
        ];
    }


    public static function providerViewSession(): array
    {
        return [
            'All' => [
                'viewerUrl'      => 'https://embed.kamihq.com/web/viewer.html?document_identifier=2kmcks',
                'viewSessionKey' => '12345',
                'sessionExpiry'  => new Alaress_Date_Format(
                    '2015-09-22T23:22:12.798Z',
                    Alaress_Date_Format::FORMAT_DATETIME_UTC
                ),
                'response'       => new Response(
                    200,
                    [],
                    json_encode([
                        'session_expiry'   => '2015-09-22T23:22:12.798Z',
                        'view_session_key' => '12345',
                        'viewer_url'       => 'https://embed.kamihq.com/web/viewer.html?document_identifier=2kmcks',
                    ])
                ),
            ],
            'No Expiry' => [
                'viewerUrl'      => 'https://embed.kamihq.com/web/viewer.html?document_identifier=3245',
                'viewSessionKey' => '54321',
                'sessionExpiry'  => null,
                'response'       => new Response(
                    200,
                    [],
                    json_encode([
                        'view_session_key' => '54321',
                        'viewer_url'       => 'https://embed.kamihq.com/web/viewer.html?document_identifier=3245',
                    ])
                ),
            ],
            'No Key' => [
                'viewerUrl'      => 'https://embed.kamihq.com/web/viewer.html?document_identifier=dter',
                'viewSessionKey' => '',
                'sessionExpiry'  => new Alaress_Date_Format(
                    '2015-09-22T23:22:12.798Z',
                    Alaress_Date_Format::FORMAT_DATETIME_UTC
                ),
                'response'       => new Response(
                    200,
                    [],
                    json_encode([
                        'session_expiry' => '2015-09-22T23:22:12.798Z',
                        'viewer_url'     => 'https://embed.kamihq.com/web/viewer.html?document_identifier=dter',
                    ])
                ),
            ],
            'URL only' => [
                'viewerUrl'      => 'https://embed.kamihq.com/web/viewer.html?document_identifier=ccsd',
                'viewSessionKey' => '',
                'sessionExpiry'  => null,
                'response'       => new Response(
                    200,
                    [],
                    json_encode([
                        'viewer_url' => 'https://embed.kamihq.com/web/viewer.html?document_identifier=ccsd',
                    ])
                ),
            ],
        ];
    }


    public static function providerViewSessionBadResponse(): array
    {
        return [
            'No URL' => [
                new Response(
                    200,
                    [],
                    json_encode([
                        'session_expiry'   => '2015-09-22T23:22:12.798Z',
                        'view_session_key' => '12345',
                    ])
                ),
            ],
            'Bad Expiry' => [
                new Response(
                    200,
                    [],
                    json_encode([
                        'session_expiry'   => '2015',
                        'viewer_url'       => 'https://embed.kamihq.com/web/viewer.html?document_identifier=ccsd',
                        'view_session_key' => '12345',
                    ])
                ),
            ],
        ];
    }


    public static function providerGetExport(): array
    {
        return [
            // An ordinary document
            'Done' => [
                'documentIdentifier' => 'ccsd',
                'status'             => 'done',
                'fileUrl'            => 'https://embed.kamihq.com/web/download.html?document_identifier=ccsd',
                'errorType'          => null,
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        "id"         => "ccsd",
                        "status"     => "done",
                        "file_url"   => "https://embed.kamihq.com/web/download.html?document_identifier=ccsd",
                        "error_type" => null,
                    ])
                ),
            ],
            // Pending
            'Pending' => [
                'documentIdentifier' => 'ccsd',
                'status'             => 'pending',
                'fileUrl'            => null,
                'errorType'          => null,
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        "id"         => "ccsd",
                        "status"     => "pending",
                        "file_url"   => null,
                        "error_type" => null,
                    ])
                ),
            ],
            // An erroneous document - still retrieved
            'Error' => [
                'documentIdentifier' => 'ccsd',
                'status'             => 'error',
                'fileUrl'            => null,
                'errorType'          => 'Bad document type',
                'response'           => new Response(
                    200,
                    [],
                    json_encode([
                        "id"         => "ccsd",
                        "status"     => "error",
                        "file_url"   => null,
                        "error_type" => 'Bad document type',
                    ])
                ),
            ],
        ];
    }


    public static function providerGetExportBadResponse(): array
    {
        return [
            'Unknown Error' => [
                'response' => new Response(
                    400,
                    [],
                    json_encode(["errors" => ["Unknown Error"],])
                ),
            ],
            'No ID' => [
                'response' => new Response(
                    200,
                    [],
                    json_encode([
                        "status"     => "error",
                        "file_url"   => null,
                        "error_type" => 'Bad document type',
                    ])
                ),
            ],
            'No Status' => [
                'response' => new Response(
                    200,
                    [],
                    json_encode([
                        "id"         => "ccsd",
                        "file_url"   => null,
                        "error_type" => 'Bad document type',
                    ])
                ),
            ],
        ];
    }


    /**
     * Create a storage object for use in tests.
     *
     * @return Storage\Model
     */
    private function getStorage()
    {
        $storage = new Storage\Model();
        $storage->setFilename('filename');
        $storage->setExtension('ext');
        return $storage;
    }


    /**
     * Create a mock filesystem proxy object.
     *
     * @return MockObject|Storage\FilesystemProxy
     */
    private function getMockFilesystemProxy()
    {
        $mock = $this->createMock(Storage\FilesystemProxy::class);
        $mock
            ->expects($this->any())
            ->method('read')
            ->willReturn('some random file contents');
        $mock
            ->expects($this->any())
            ->method('readStream')
            ->willReturnCallback(function () {
                return fopen('data://text/plain,' . 'some random file contents', 'rb');
            });
        return $mock;
    }


    /**
     * Helper function for getting a mock client
     *
     * Mock clients will always have one pre-built response.
     *
     * @param ResponseInterface $response
     * @return Client
     */
    private function getMockClient(ResponseInterface $response): Client
    {
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willReturn($response);
        return $mockClient;
    }


    /**
     * Helper function for getting a mock client that throws an exception.
     *
     * For testing, this is always a destination unreachable exception.
     *
     * @return Client
     */
    private function getMockClientWithConnectException(): Client
    {
        $mockClient = $this->getMockBuilder(Client::class)->getMock();
        $mockClient
            ->method('send')
            ->willThrowException(
                new ConnectException('Destination unreachable', new Request('GET', 'kami.com'))
            );
        return $mockClient;
    }
}
