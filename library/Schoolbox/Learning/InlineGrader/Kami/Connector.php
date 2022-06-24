<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\InlineGrader\Kami\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\User;
use stdClass;

/**
 * Gateway between Schoolbox and Kami
 */
class Connector implements IConnector
{
    // Intentional trailing slash (@see http://docs.guzzlephp.org/en/stable/quickstart.html#creating-a-client)
    // Correspond to API paths. @see http://docs.kamiembeddingapi.apiary.io
    public const BASE_URI = 'https://api.notablepdf.com/';

    public const PATH_UPLOAD_DOCUMENT_MULTIPART = 'upload/embed/documents';
    public const PATH_LIST_DOCUMENTS = 'embed/documents';
    public const PATH_GET_DOCUMENT = 'embed/documents/';
    public const PATH_DELETE_DOCUMENT = 'embed/documents/';

    // How long should view sessions last for by default?
    public const DEFAULT_SESSION_EXPIRY_HOURS = 1;

    // Export types
    public const EXPORT_TYPE_ANNOTATION = 'annotation'; // Annotations embedded into the document as PDF Annotations.
    public const EXPORT_TYPE_INLINE = 'inline';         // Annotations drawn on to document. Useful for printing.

    /** @var Client */
    private $client;

    /** @var bool */
    private bool $enabled;

    /**
     * Shared by all schoolbox instances. There is an alternative api-key available for testing to keep
     * test documents out of the production kami account. See SA-4489
     *
     * @var string
     */
    private $apiKey;

    /**
     * Prepended to schoolbox user.id to form a kami comment author.id to uniquely identify schoolbox users in Kami
     *
     * @var string
     */
    private $siteSchoolCode;


    /**
     * @param ClientInterface $client
     * @param null|bool       $enabled Will be null when invoked by update scripts prior to config addition
     * @param null|string     $apiKey
     * @param null|string     $siteSchoolCode
     */
    public function __construct(ClientInterface $client, ?bool $enabled, ?string $apiKey, ?string $siteSchoolCode)
    {
        $this->client         = $client;
        $this->enabled        = $enabled ?? null !== $apiKey; # TODO remove coalesce once all clients upgrade to 20.2.10
        $this->apiKey         = null === $apiKey ? '' : trim($apiKey);
        $this->siteSchoolCode = null === $siteSchoolCode ? '' : trim($siteSchoolCode);
    }


    /**
     * Is Kami enabled for this school? (Controlled by Puppet)
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        // Any non-empty value is considered 'enabled', regardless of whether it is 'valid'
        return $this->enabled;
    }


    /**
     * Is Kami online?
     *
     * Will be false if:
     * - api key is empty, or
     * - api key is invalid, or
     * - we cannot connect to the Kami API
     *
     * @return bool
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     */
    public function isOnline(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $request = new Request('GET', $this->getPathGetDocument('FAKE_DOC_ID'));
        try {
            $response = $this->client->send($request, $this->getOptions(['timeout' => 2]));
        } catch (GuzzleException $e) {
            return false;
        }
        return $this->isValidNotFound($response);
    }


    /**
     * Upload a file into Kami and return the newly assigned Kami Document Identifier
     *
     * Note that this method cost 4c each time you hit the production or proxy server
     *
     * @param IStorage        $storage
     * @param FilesystemProxy $filesystemProxy
     * @return Document
     * @throws \InvalidArgumentException If cannot convert the request or response to a string
     * @throws Exception\EmptyApiKeyException If no Kami API key configured
     * @throws Exception\ResponseDecodeException If cannot decode the Kami response
     * @throws Exception\ApiException if response doesn't contain required fields
     * @throws Exception\InvalidStatusException if response status is invalid
     * @throws Exception\NotFound If document somehow does not exist in Kami after being uploaded
     * @throws GuzzleException
     */
    public function uploadDocument(IStorage $storage, FilesystemProxy $filesystemProxy): Document
    {
        $this->assertEnabled();

        // The 'name' and 'document' fields are required.
        // Note that storage files in multipart form data must be sent as string
        // values (and not resources), because files can't be posted from
        // non-local resources.
        $options  = [
            RequestOptions::MULTIPART => [
                [
                    'name'     => 'name',
                    'contents' => $storage->getBasename(),
                ],
                [
                    'name'     => 'document',
                    'contents' => $filesystemProxy->read($storage),
                    'filename' => $storage->getBasename(),
                ],
            ],
        ];
        $request  = new Request('POST', $this->getPathUploadDocument());
        $response = $this->client->send($request, $this->getOptions($options));

        if ($this->isSuccessful($response)) {
            $decoded = $this->decodeResponseContent($response->getBody());
            return $this->buildDocument($decoded);
        }

        // Bad response, but not a simple 'not found'?
        if (!$this->isValidNotFound($response)) {
            $this->generateApiException($request, $response);
        }
        throw new Exception\NotFound("Kami Document not uploaded", $request, $response);
    }


    /**
     * Get a list of all documents uploaded to Kami.
     *
     * Any documents that don't pass parsing are ignored, instead of raising
     * an exception; I don't want to crash the entire list just because one
     * document failed to parse.
     *
     * @return GenericCollection|Document[]
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws ClientException If cannot fetch documents for reasons other than simply not existing.
     * @throws GuzzleException
     */
    public function listDocuments(): GenericCollection
    {
        $this->assertEnabled();
        $documents = new GenericCollection(Document::class);

        $request  = new Request('GET', self::PATH_LIST_DOCUMENTS);
        $response = $this->client->send($request, $this->getOptions());

        /** @var \StdClass[] $decoded */
        $decoded  = $this->decodeResponseContent($response->getBody())->documents;

        foreach ($decoded as $obj) {
            try {
                $document = $this->buildDocument($obj);
            } catch (Exception\IKamiException $e) {
                // This document failed to parse: nothing to do but...
                continue;
            }
            $documents[$document->getId()] = $document;
        }
        return $documents;
    }


    /**
     * Return the specified document from Kami if it exists there. Otherwise throws an exception.
     *
     * @param string $documentId
     * @return Document
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws \InvalidArgumentException If cannot convert the request or response to a string
     * @throws Exception\NotFound If document does not exist in Kami
     * @throws Exception\InvalidStatusException if document's status is invalid
     * @throws GuzzleException
     */
    public function getDocument(string $documentId): Document
    {
        $this->assertEnabled();
        $request  = new Request('GET', $this->getPathGetDocument($documentId));
        $response = $this->client->send($request, $this->getOptions());

        if ($this->isSuccessful($response)) {
            $decoded = $this->decodeResponseContent($response->getBody());
            return $this->buildDocument($decoded);
        }

        // Bad response, but not a simple 'not found'?
        if (!$this->isValidNotFound($response)) {
            $this->generateApiException($request, $response);
        }
        throw new Exception\NotFound("Kami Document not found. Id: $documentId", $request, $response);
    }


    /**
     * Delete an uploaded document. This will remove the file from Kami's cloud storage.
     *
     * @param string $documentId
     * @return bool Whether a mutation occurred. (will always return true, if it fails expect an exception)
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws \InvalidArgumentException If cannot convert the request or response to a string
     * @throws Exception\NotFound If document does not exist in Kami
     * @throws GuzzleException
     */
    public function deleteDocument(string $documentId): bool
    {
        $this->assertEnabled();

        $request  = new Request('DELETE', $this->getPathDeleteDocument($documentId));
        $response = $this->client->send($request, $this->getOptions());

        if ($this->isValidNotFound($response)) {
            throw new Exception\NotFound("Kami Document not found. Id: $documentId", $request, $response);
        }

        if (!$this->isSuccessful($response)) {
            $this->generateApiException($request, $response);
        }
        return true;
    }


    /**
     * Create a time-limited session and returns a URL you can embed in your page.
     *
     * Note that this implementation does not permit creation of a session that does not expire.
     *
     * @param string $documentId
     * @param User   $user
     * @param bool   $editable
     * @param string $theme     Sets the theme to use when viewing the document
     * @param bool   $showSave  Hides the save (download) button
     * @param bool   $showPrint Hides the print button
     * @param bool   $showHelp  Hides the help button
     * @param bool   $showMenu  Hides the menu button
     * @return Session
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\EmptySiteSchoolCode If 'site_school_code' is empty
     * @throws Exception\ApiException If API response is unable to be processed by us
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws \InvalidArgumentException If cannot convert the request or response to a string
     * @throws Exception\NotFound If document does not exist in Kami
     * @throws GuzzleException
     */
    public function viewSession(
        string $documentId,
        User $user,
        bool $editable = true,
        string $theme = IConnector::SESSION_THEME_LIGHT,
        bool $showSave = true,
        bool $showPrint = true,
        bool $showHelp = true,
        bool $showMenu = true
    ): Session {
        $this->assertEnabled();
        $this->assertValidSiteSchoolCode();

        // When should the view session expire?
        $expires = Alaress_Date_Format::now()->addHours(self::DEFAULT_SESSION_EXPIRY_HOURS);
        $options  = [
            RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            RequestOptions::BODY    =>  \GuzzleHttp\Utils::jsonEncode([
                'document_identifier' => $documentId,
                'user'                => [
                    'name'    => "{$user->getFirstname()} {$user->getLastname()}",
                    'user_id' => "{$this->siteSchoolCode}-{$user->getId()}",
                ],
                'expires_at'          => $expires->getDateTimeObject()->utc()->format(Alaress_Date_Format::FORMAT_DATETIME_UTC),
                'viewer_options'      => [
                    'theme'                  => $theme,
                    'show_save'              => $showSave,
                    'show_print'             => $showPrint,
                    'show_help'              => $showHelp,
                    'show_menu'              => $showMenu,
                    'enable_annotation_bank' => $editable,
                ],
                'editable'            => $editable,
            ]),
        ];
        $request  = new Request('POST', self::BASE_URI . 'embed/sessions');
        $response = $this->client->send($request, $this->getOptions($options));
        if ($this->isValidNotFound($response)) {
            throw new Exception\NotFound("Kami Document not found. Id: $documentId", $request, $response);
        }
        return $this->buildViewSession($this->decodeResponseContent($response->getBody()));
    }


    /**
     * Request generation of a PDF export with annotations embedded as PDF annotations
     *
     * @param string $documentId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If document does not exist in Kami
     */
    public function createAnnotatedExport(string $documentId): Export
    {
        return $this->createExport($documentId, self::EXPORT_TYPE_ANNOTATION);
    }


    /**
     * Request generation of a PDF export suitable for printing
     *
     * @param string $documentId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If document does not exist in Kami
     */
    public function createInlineExport(string $documentId): Export
    {
        return $this->createExport($documentId, self::EXPORT_TYPE_INLINE);
    }


    /**
     * Get the current status of a previously requested document export.
     *
     * @param string $exportId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If export does not exist in Kami
     */
    public function getExport(string $exportId): Export
    {
        $this->assertEnabled();
        $request  = new Request('GET', self::BASE_URI . "embed/exports/$exportId");
        $response = $this->client->send($request, $this->getOptions());
        if ($this->isValidNotFound($response)) {
            throw new Exception\NotFound("Kami Document not found. Id: $exportId", $request, $response);
        }
        return $this->buildExport($this->decodeResponseContent($response->getBody()));
    }


    /**
     * @param string $documentId
     * @param string $exportType
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If document does not exist in Kami
     */
    private function createExport(string $documentId, string $exportType): Export
    {
        $this->assertEnabled();
        $options  = [
            RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            RequestOptions::BODY    => json_encode([
                'document_identifier' => $documentId,
                'export_type'         => $exportType,
            ]),
        ];
        $request  = new Request('POST', self::BASE_URI . 'embed/exports');
        $response = $this->client->send($request, $this->getOptions($options));
        if ($this->isValidNotFound($response)) {
            throw new Exception\NotFound("Kami Document not found. Id: $documentId", $request, $response);
        }
        return $this->buildExport($this->decodeResponseContent($response->getBody()));
    }


    /**
     * Get the authorisation header to use for all requests
     *
     * @return string[]
     */
    private function getHeaderAuthorisation(): array
    {
        return ['Authorization' => "Token {$this->apiKey}"];
    }


    /**
     * Build the path for the 'Get Document' call
     *
     * @param string $documentId
     * @return string
     */
    private function getPathGetDocument(string $documentId): string
    {
        return self::PATH_GET_DOCUMENT . $documentId;
    }


    /**
     * Build the path for the 'Delete Document' call
     *
     * @param string $documentId
     * @return string
     */
    private function getPathDeleteDocument(string $documentId): string
    {
        return self::PATH_DELETE_DOCUMENT . $documentId;
    }


    /**
     * Build the path for the 'Upload Document' call
     *
     * @return string
     */
    private function getPathUploadDocument(): string
    {
        return self::PATH_UPLOAD_DOCUMENT_MULTIPART;
    }


    /**
     * Decode Kami server response
     *
     * @param StreamInterface $response
     * @return stdClass
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     */
    private function decodeResponseContent(StreamInterface $response): stdClass
    {
        /* Runtime exception can be thrown when there's a problem in getContents()
         */
        try {
            $payload = $response->getContents();
        } catch (\RuntimeException $e) {
            throw new Exception\ResponseDecodeException($e->getMessage(), $e->getCode());
        }

        /* $payload can be null if it can't be json_decode()'d
         */
        $decodedPayload = json_decode($payload, false);

        if (!is_object($decodedPayload)) {
            throw new Exception\ResponseDecodeException('Cannot decode kami payload; not json: ' . $payload);
        }

        return $decodedPayload;
    }


    /**
     * Ensure Kami is enabled before attempting API calls
     *
     * @throws Exception\EmptyApiKeyException If API key not configured
     */
    private function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw new Exception\EmptyApiKeyException('Kami is not enabled.');
        }
    }


    /**
     * Ensure configured 'site_school_code' is not empty so we can generate unique kami comment author id
     *
     * @throws Exception\EmptySiteSchoolCode If ''site_school_code' not configured
     */
    private function assertValidSiteSchoolCode(): void
    {
        // Ensure this is not empty so we can generate a unique comment author identifier in Kami
        if ('' === $this->siteSchoolCode) {
            throw new Exception\EmptySiteSchoolCode("'site_school_code' cannot be empty");
        }
    }


    /**
     * @param stdClass $obj
     * @return Document
     * @throws Exception\InvalidStatusException if document's status is invalid
     * @throws Exception\ApiException if $obj doesn't contain required fields
     */
    private function buildDocument(stdClass $obj): Document
    {
        // All these fields must exist in order to create this doc
        $this->assertProperties($obj, ['document_identifier', 'name', 'file_status', 'file_error_message', 'created_at']);

        // And date must be UTC
        try {
            $createdDate = new Alaress_Date_Format($obj->created_at, Alaress_Date_Format::FORMAT_DATETIME_UTC);
        } catch (\InvalidArgumentException $e) {
            throw new Exception\ApiException("Property created_at is not able to be parsed as a date");
        }
        return new Document(
            $obj->document_identifier,
            $obj->name,
            $obj->file_status,
            $obj->file_error_message,
            $createdDate
        );
    }


    /**
     * Does this response have a success (2xx) status code?
     *
     * For now, we assume that Kami will not return an informational (1xx) or
     * redirect (3xx) status.
     *
     * @param ResponseInterface $response
     * @return bool
     */
    private function isSuccessful(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200) {
            return false;
        }
        if ($statusCode >= 300) {
            return false;
        }
        return true;
    }


    /**
     * Does the response indicate a valid URL that simply returned no data?
     * (as opposed to an invalid URL)
     *
     * @param ResponseInterface $response
     * @return bool
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     */
    private function isValidNotFound(ResponseInterface $response): bool
    {
        if (!$response || 404 !== $response->getStatusCode()) {
            return false;
        }
        $body = $this->decodeResponseContent($response->getBody());
        if (!is_object($body) || !property_exists($body, 'errors')) {
            return false;
        }
        return 'Record Not Found' === $body->errors[0];
    }


    /**
     * Generate an ApiException containing the request and response contents
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @throws Exception\ApiException
     * @throws \InvalidArgumentException If cannot convert the request or response to a string
     */
    private function generateApiException(RequestInterface $request, ResponseInterface $response): void
    {
        $requestString  = Psr7\str($request);
        $responseString = Psr7\str($response);
        throw new Exception\ApiException("Unable to fetch document.\n{$requestString}\n$responseString");
    }


    /**
     * Convert a response body into a view session object
     *
     * @param stdClass $obj
     * @return Session
     * @throws Exception\ApiException if $obj doesn't contain required field viewer_url
     *                                if $obj->session_expiry exists but isn't parseable
     */
    private function buildViewSession(stdClass $obj): Session
    {
        // view_session_key and session_expiry are optional, viewer_url is not
        $this->assertProperties($obj, ['viewer_url',]);

        $sessionKey = property_exists($obj, 'view_session_key') ? $obj->view_session_key : '';

        if (property_exists($obj, 'session_expiry')) {
            try {
                $sessionExpiry = new Alaress_Date_Format($obj->session_expiry, Alaress_Date_Format::FORMAT_DATETIME_UTC);
            } catch (\InvalidArgumentException $e) {
                throw new Exception\ApiException("Property session_expiry is not able to be parsed as a date");
            }
        } else {
            $sessionExpiry = new Alaress_Date_NullDate();
        }
        return new Session($sessionKey, $obj->viewer_url, $sessionExpiry);
    }


    /**
     * Convert a response body into a view session object
     *
     * @param stdClass $obj
     * @return Export
     * @throws Exception\ApiException if $obj doesn't contain required fields id and status
     * @throws Exception\InvalidStatusException if export's status is invalid
     */
    private function buildExport(stdClass $obj): Export
    {
        $this->assertProperties($obj, ['id', 'status',]);
        return new Export(
            $obj->id,
            $obj->status,
            $obj->file_url,
            $obj->error_type
        );
    }


    /**
     * Get merged request options
     *
     * @param array $extraOptions
     * @return array
     */
    private function getOptions(array $extraOptions = []): array
    {
        return array_merge_recursive($extraOptions, $this->getBaseOptions());
    }


    /**
     * Get options common to all authenticated requests
     *
     * @return array
     */
    private function getBaseOptions(): array
    {
        return [
            RequestOptions::HEADERS     => $this->getHeaderAuthorisation(),
            RequestOptions::HTTP_ERRORS => false,
            'base_uri'                  => static::BASE_URI,
        ];
    }


    /**
     * Assert that all properties in $properties exist in $obj
     *
     * @param stdClass $obj
     * @param array    $properties
     * @throws Exception\ApiException if not all do
     */
    private function assertProperties(stdClass $obj, array $properties): void
    {
        $missingProperties = array_filter(
            $properties,
            function ($property) use ($obj) {
                return !property_exists($obj, $property);
            }
        );
        if (!empty($missingProperties)) {
            throw new Exception\ApiException(
                sprintf(
                    "Required properties (%s) not found",
                    implode(', ', $missingProperties)
                )
            );
        }
    }
}
