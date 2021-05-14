<?php
/**
 * Yank down Google Drive files to upload into schoolbox
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\File\Remote;

use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use OutOfBoundsException;
use Schoolbox\IConfigLoader;

class GDriveFetcher implements FetcherInterface
{
    /**
     * Google API Endpoint
     */
    private const GOOGLE_API = 'https://www.googleapis.com/drive/v3';

    /**
     * The mimetypeMapping key that stores file extensions
     */
    private const CONTEXT_EXTENSION = 'ext';

    /**
     * The mimetypeMapping key that stores exporting file mimetypes
     */
    private const CONTEXT_EXPORT_MIMETYPE = 'exportMimetype';

    /**
     * The mimetypeMapping key that stores download urls (exportLinks)
     */
    private const CONTEXT_EXPORTLINKS = 'exportLinks';

    /**
     * Mimetypes and their corresponding exportLinks, ext values
     * https://developers.google.com/drive/api/v3/ref-export-formats?hl=en
     * @var array
     */
    private const MIMETYPE_MAPPING = [

        /* Powerpoint slides
         */
        'application/vnd.google-apps.presentation' => [
            self::CONTEXT_EXPORT_MIMETYPE => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            self::CONTEXT_EXTENSION       => 'pptx',
        ],

        /* Word doc
         */
        'application/vnd.google-apps.document'     => [
            self::CONTEXT_EXPORT_MIMETYPE => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::CONTEXT_EXTENSION       => 'docx',
        ],

        /* Spreadsheets
         */
        'application/vnd.google-apps.spreadsheet'  => [
            self::CONTEXT_EXPORT_MIMETYPE => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CONTEXT_EXTENSION       => 'xlsx',
        ],
    ];

    /**
     * @var bool
     */
    private bool $allowPdfExport;

    /**
     */
    private Client $client;

    /**
     * @var array
     */
    private array $metaCache = [];

    /**
     * http header
     */
    private array $httpHeaders;

    /**
     * Constructor
     *
     * @param IConfigLoader $conf
     * @param string        $httpAuth       an authorization header if not using an anonymous connection
     * @param bool          $allowPdfExport Can this export PDFs?
     */
    public function __construct(
        IConfigLoader $conf,
        string $httpAuth = '',
        bool $allowPdfExport = true
    ) {
        $proxy                              = new \Proxy\Server($conf);
        $this->client                       = new Client(
            [
                RequestOptions::HEADERS => ['Authorization' => $httpAuth],
                'curl'                  => $proxy->buildCurlOptions(),
                'timeout'               => 10,
            ]
        );
        $this->httpHeaders['Authorization'] = $httpAuth;
        $this->allowPdfExport               = $allowPdfExport;
    }

    /**
     * The config option "Google Drive Always Convert To PDF" is on
     *
     * @param array $metadata
     * @return bool
     */
    private function isPdfExportEnabled(array $metadata): bool
    {
        return $this->allowPdfExport && isset($metadata[self::CONTEXT_EXPORTLINKS]['application/pdf']);
    }

    /**
     * Retrieves the metadata for the given fileId
     * https://developers.google.com/drive/api/v3/reference/files?hl=en
     * @param int  $fileId
     * @param bool $refresh
     * @return array
     */
    public function fetchMetaData($fileId, $refresh = false): array
    {
        if ($refresh || empty($this->metaCache[$fileId])) {
            $params = [
                'fields'            => '*',
                'supportsAllDrives' => 'true',
            ];
            $url    = self::GOOGLE_API . "/files/{$fileId}?" . http_build_query($params);

            try {
                $response = $this->client->get($url);
                if ($response->getStatusCode() !== 200) {
                    throw new DomainException("Failed to get metadata for file: {$fileId}");
                }
            } catch (GuzzleException $e) {
                throw new DomainException($e);
            }

            $fetchedMetaData = json_decode($response->getBody(), true);

            // copy name to title to support the key change from v2 to v3
            $fetchedMetaData['title'] = $fetchedMetaData['name'];
            $this->metaCache[$fileId] = $fetchedMetaData;
        }

        /* On some mysterious occasions google drive will return you an error payload
         * If any were encountered, detonate with it - this crashes everything anyway
         */
        $metadata = $this->metaCache[$fileId];

        if (isset($metadata['error'])) {
            throw new DomainException($this->getErrorMessage($metadata));
        }

        return $metadata;
    }

    /**
     * Fetches Permission
     * https://developers.google.com/drive/api/v3/reference/permissions?hl=en
     * @param string $fileId
     * @return mixed
     */
    public function fetchPermissions(string $fileId)
    {
        $params = [
            'fields'            => '*',
            'supportsAllDrives' => 'true',
        ];
        $url    = self::GOOGLE_API . "/files/{$fileId}/permissions?" . http_build_query($params);

        try {
            $response = $this->client->get($url);
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
        }
        return null;
    }

    /**
     * Sets permission to a google file
     * https://developers.google.com/drive/api/v3/reference/permissions?hl=en
     * @param string $fileId
     * @param string $accessRole
     * @param string $email
     */
    public function setPermissions(string $fileId, string $accessRole, string $email): void
    {
        $result = $this->callSetPermissions($fileId, $accessRole, $email, false);
        if ($this->getErrorMessage($result)) {
            // retry with notification on
            $result = $this->callSetPermissions($fileId, $accessRole, $email, true);
            if ($error = $this->getErrorMessage($result)) {
                throw new DomainException($error);
            }
        }
    }

    /**
     * @param string $fileId
     * @param string $accessRole
     * @param string $email
     * @param bool   $notify
     * @return array
     */
    private function callSetPermissions(string $fileId, string $accessRole, string $email, bool $notify): array
    {
        $params = [
            'fileId'                => $fileId,
            'fields'                => '*',
            'supportsAllDrives'     => 'true',
            'sendNotificationEmail' => $notify ? 'true' : 'false', // this is necessary for non google accounts
        ];

        $payload = [
            'role'         => $accessRole,
            'type'         => 'user',
            'emailAddress' => $email,
        ];

        $this->httpHeaders['Content-Type'] = 'application/json';

        $url = self::GOOGLE_API . "/files/{$fileId}/permissions?" . http_build_query($params);

        try {
            $response = $this->client->post(
                $url,
                [
                    RequestOptions::HEADERS => $this->httpHeaders,
                    RequestOptions::BODY    => json_encode($payload),
                ]

            );
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $error = $e->getResponse()->getBody()->getContents();
            return json_decode($error, true);
        }
    }

    /**
     * Restore icon to attachzone for this file
     * The mimeType field doesn't contain a string that's known to our allowedFileTypes.mime
     * so we re-write this particular field in a way that supports allowedFileTypes.mime
     * Returns null, when the file is not downloadable
     *
     * @param array $metadata
     * @return string|null
     */
    public function determineDownloadUrl(array $metadata): ?string
    {
        if (isset($metadata['capabilities']['canDownload']) && !$metadata['capabilities']['canDownload']) {
            return null;
        }

        if ($this->isPdfExportEnabled($metadata) && $metadata[self::CONTEXT_EXPORTLINKS]['application/pdf']) {
            return $metadata[self::CONTEXT_EXPORTLINKS]['application/pdf'];
        }

        $allowedFilesMime = isset(self::MIMETYPE_MAPPING[$metadata['mimeType']])
            ? self::MIMETYPE_MAPPING[$metadata['mimeType']][self::CONTEXT_EXPORT_MIMETYPE]
            : null;

        if (($allowedFilesMime !== null) && $metadata[self::CONTEXT_EXPORTLINKS][$allowedFilesMime]) {
            return $metadata[self::CONTEXT_EXPORTLINKS][$allowedFilesMime];
        }

        if (is_array($metadata[self::CONTEXT_EXPORTLINKS]) && !empty($metadata[self::CONTEXT_EXPORTLINKS])) {
            return array_shift($metadata[self::CONTEXT_EXPORTLINKS]);
        }

        return self::GOOGLE_API . "/files/{$metadata['id']}?alt=media";
    }

    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @return string
     */
    public function fetchToFile($fileId): string
    {
        $metadata = $this->fetchMetaData($fileId);
        $url      = $this->determineDownloadUrl($metadata);

        if (!$url) {
            // if this does not provide export mimetype, this cannot be downloaded
            throw new DomainException('This file is restricted and cannot be downloaded.');
        }

        $destination = tempnam(sys_get_temp_dir(), 'gdrive-');

        /* null or false $destination when tempnam fails for some reason
        */
        if (!$destination) {
            throw new OutOfBoundsException('Could not determine destination for temp file');
        }

        /* If you can't open the $destination tempnam() made
         */
        if (!($fp = fopen($destination, 'w'))) {
            throw new OutOfBoundsException('Could not open target');
        }
        try {
            $this->client->get($url, [RequestOptions::SINK => $fp]);
        } catch (GuzzleException $e) {
            throw new DomainException($e);
        }

        if (mime_content_type($destination) === 'application/json') {
            // for some reason, Google output error in the downloaded file
            $fetchedData  = file_get_contents($destination);
            $errorMessage = $this->getErrorMessage(json_decode($fetchedData, true));
            if (!empty($errorMessage)) {
                throw new DomainException($errorMessage);
            }
        }

        return $destination;
    }


    /**
     * Derive the file name from the metadata
     *
     * @param array $metadata
     * @return string|null
     */
    public function deriveFileName(array $metadata): ?string
    {
        /* If this is one of the google docs filetypes we know about
         */
        if (isset(self::MIMETYPE_MAPPING[$metadata['mimeType']])) {
            /* If there is a PDF-export option for this file (and the config is set to do pdf export),
             * always give that preference
             *
             * There seems to be different behaviour for pdf-able files and other ones
             */
            if ($this->isPdfExportEnabled($metadata)) {
                return "{$metadata['title']}.pdf";
            }

            /* But if pdf export is disabled, infer a meaningful filename instead
             * so you can load it into Office etc.
             */
            $extension = self::MIMETYPE_MAPPING[$metadata['mimeType']][self::CONTEXT_EXTENSION];

            return "{$metadata['title']}.{$extension}";
        }

        /* If you got here, it's some other kind of file
         *
         * If the metadata field "fileExtension" contains text, investigate the contents of
         * "originalFilename" and "fileExtension"
         */
        if ('' !== (string)$metadata['fileExtension']) {
            /* "originalFilename" might already contain the extension if you uploaded a file
             * If the last characters of "originalFilename" are the same as "fileExtension"
             * then just return "originalFilename" with no alterations required
             */
            $extIndex = strrpos($metadata['originalFilename'], '.');

            if (false !== strpos($metadata['originalFilename'], (string)$metadata['fileExtension'], $extIndex)) {
                return $metadata['originalFilename'];
            }

            /* Otherwise it might not have the extension in "originalFilename" at all, such as
             * that "Getting Started" pdf; assume a concatenation of "title" and "fileExtension"
             * to make a full filename
             */
            return "{$metadata['title']}.{$metadata['fileExtension']}";
        }
        return null;
    }

    /**
     * @param array $result
     * @return string|null
     */
    private function getErrorMessage(array $result): ?string
    {
        if (!isset($result['error'])) {
            return null;
        }
        $errors = [];

        /** @var array $errorIndex */
        $errorIndex = $result['error']['errors'];

        foreach ($errorIndex as $currentError) {
            $errors[] = $currentError['message'];
        }

        return implode('; ', $errors);
    }
}
