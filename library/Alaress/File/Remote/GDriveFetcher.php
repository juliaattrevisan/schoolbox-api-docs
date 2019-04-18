<?php
/**
 * Yank down Google Drive files to upload into schoolbox
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\File\Remote;

use DomainException;
use OutOfBoundsException;
use \Schoolbox\Config\Loader as ConfigLoader;

class GDriveFetcher implements FetcherInterface
{
    /**
     * Google API Endpoint
     */
    private const GOOGLE_API = 'https://www.googleapis.com/drive/v2';

    /**
     * The mimetypeMapping key that stores file extensions
     */
    private const CONTEXT_EXTENSION = 'ext';

    /**
     * The mimetypeMapping key that stores download urls (exportLinks)
     */
    private const CONTEXT_EXPORTLINKS = 'exportLinks';

    /**
     * Metadata fields that might contain a download url
     * Google API sometimes changes causing fields to no longer exist
     */
    private const FIELDS_EXPORTLINKS = ['downloadUrl'];

    /**
     * Mimetypes and their corresponding exportLinks, ext values
     *
     * @var array
     */
    private const MIMETYPE_MAPPING = [

        /* Powerpoint slides
         */
        'application/vnd.google-apps.presentation' => [
            self::CONTEXT_EXPORTLINKS => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            self::CONTEXT_EXTENSION => 'pptx',
        ],

        /* Word doc
         */
        'application/vnd.google-apps.document' => [
            self::CONTEXT_EXPORTLINKS => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::CONTEXT_EXTENSION => 'docx',
        ],

        /* Spreadsheets
         */
        'application/vnd.google-apps.spreadsheet' => [
            self::CONTEXT_EXPORTLINKS => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CONTEXT_EXTENSION => 'xlsx'
        ]
    ];

    /**
     * @var ConfigLoader
     */
    private $configLoader;

    /**
     * @var false|resource
     */
    private $ch;

    /**
     * @var string
     */
    private $dir;

    /**
     * @var array
     */
    private $metaCache = [];

    /**
     * @param string   $storageDir - the target storage directory: typically the result of `sys_get_temp_dir()`
     * @param resource $curl       - a custom curl handle if a default won't suffice
     * @param string   $httpAuth   - an authorization header if not using an anonymous connection
     */
    public function __construct(string $storageDir, $curl = null, string $httpAuth = '')
    {
        $this->dir = $storageDir;
        $this->ch  = $curl ?: curl_init();

        if ($httpAuth) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $httpAuth
            ));
        }

        $this->configLoader = new ConfigLoader();
    }

    /**
     * Clean up the cURL handle
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * The config option "Google Drive Always Convert To PDF" is on
     *
     * @param array $metadata
     * @return bool
     */
    private function isPdfExportEnabled(array $metadata): bool
    {
        return $this->configLoader->get('googledrive_export_pdf') && isset($metadata[self::CONTEXT_EXPORTLINKS]['application/pdf']);
    }

    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int  $fileId
     * @param bool $refresh
     * @return array
     * @throws DomainException
     */
    public function fetchMetaData($fileId, $refresh = false): array
    {
        if ($refresh || empty($this->metaCache[$fileId])) {
            $ch = $this->curlPrepare(array(
                CURLOPT_URL            => self::GOOGLE_API . "/files/{$fileId}",
                CURLOPT_RETURNTRANSFER => 1,
            ));

            $this->metaCache[$fileId] = json_decode(curl_exec($ch), true);
            curl_close($ch);
        }

        /* On some mysterious occasions google drive will return you an error payload
         * If any were encountered, detonate with it - this crashes everything anyway
         */
        $metadata = $this->metaCache[$fileId];

        if (isset($metadata['error'])) {
            $errors = [];

            /** @var array $errorIndex */
            $errorIndex = $metadata['error']['errors'];

            foreach ($errorIndex as $currentError) {
                $errors[] = $currentError['message'];
            }

            $errorMessage = implode('; ', $errors);

            throw new DomainException($errorMessage);
        }

        return $metadata;
    }


    /**
     * Restore icon to attachzone for this file
     * The mimeType field doesn't contain a string that's known to our allowedFileTypes.mime
     * so we re-write this particular field in a way that supports allowedFileTypes.mime
     *
     * @param array $metadata
     * @return string
     */
    public function determineMimeIcon(array $metadata): string
    {
        $allowedFilesMime = self::MIMETYPE_MAPPING[$metadata['mimeType']][self::CONTEXT_EXPORTLINKS] ?? null;

        if ($allowedFilesMime) {
            return $allowedFilesMime;
        }

        return $metadata['mimeType'];
    }

    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @return string
     * @throws DomainException
     * @throws OutOfBoundsException
     */
    public function fetchToFile($fileId): string
    {
        $metadata = $this->fetchMetaData($fileId);

        if (isset($metadata['labels']['restricted']) && $metadata['labels']['restricted'] == true) {
            // if this file has been flagged by Drive as "restricted", disallow any download
            return null;
        } elseif ($this->isPdfExportEnabled($metadata)) {
            // if there is a PDF-export option for this file (and the config is set to do pdf export), always give that preference
            $url = $metadata[self::CONTEXT_EXPORTLINKS]['application/pdf'];
        } else {

            /* Inspect metadata fields to find a download url
             */
            $url = $this->inspectExportLinks($metadata);
        }

        if (!$url) {
            // no valid URL was found
            throw new OutOfBoundsException('No url found for download');
        }

        $destination = tempnam($this->dir, 'gdrive-');

        if (!($fp = fopen($destination, 'w'))) {
            throw new OutOfBoundsException('Could not open target');
        }

        $ch = $this->curlPrepare(array(
            CURLOPT_URL  => $url,
            CURLOPT_FILE => $fp,
        ));

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $destination;
    }

    /**
     * Inspect metadata fields to find a download url
     *
     * @param array $metadata
     * @return string
     */
    private function inspectExportLinks(array $metadata): string
    {
        if (isset(self::MIMETYPE_MAPPING[$metadata['mimeType']])) {
            $key = self::MIMETYPE_MAPPING[$metadata['mimeType']][self::CONTEXT_EXPORTLINKS];

            return $metadata[self::CONTEXT_EXPORTLINKS][$key];
        }

        foreach (self::FIELDS_EXPORTLINKS as $field) {
            if (!empty($metadata[$field])) {
                return $metadata[$field];
            }
        }

        /* If you got here, the api no longer provides the fields we were originally looking at
         * https://developers.google.com/drive/api/v2/reference/
         */
        trigger_error('Google api fields for exportLinks not found: check api docs', E_USER_WARNING);

        return '';
    }


    /**
     * Derive the file name from the metadata
     *
     * @param array $metadata
     * @return string
     */
    public function deriveFileName(array $metadata): string
    {
        /* if there is a PDF-export option for this file (and the config is set to do pdf export),
         * always give that preference
         *
         * There seems to be different behaviour for pdf-able files and other ones
         */
        if ($this->isPdfExportEnabled($metadata) && isset(self::MIMETYPE_MAPPING[$metadata['mimeType']])) {
            return "{$metadata['title']}.pdf";
        }

        return $metadata['title'];
    }

    /**
     * @param array $opts
     * @return resource
     */
    private function curlPrepare(array $opts)
    {
        $ch = curl_copy_handle($this->ch);
        curl_setopt_array($ch, $opts);

        return $ch;
    }
} 
