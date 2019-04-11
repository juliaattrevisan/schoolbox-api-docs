<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\File\Remote;


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
     * Metadata fields that might contain a file extension
     * Google API sometimes changes causing fields to no longer exist
     */
    private const FIELDS_EXTENSION = ['fileExtension', 'fullFileExtension'];
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
    private $mimetypeMapping = [
        /* Powerpoint slides
         */
        'application/vnd.google-apps.presentation' => [
            'exportLinks' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ext' => 'pptx',
        ],
        /* Word doc
         */
        'application/vnd.google-apps.document' => [
            'exportLinks' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ext' => 'docx',
        ],
        /* Spreadsheets
         */
        'application/vnd.google-apps.spreadsheet' => [
            'exportLinks' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ext' => 'xlsx'
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
    protected $metaCache = [];

    /**
     * @param string   $storageDir - the target storage directory: typically the result of `sys_get_temp_dir()`
     * @param resource $curl       - a custom curl handle if a default won't suffice
     * @param string   $httpAuth   - an authorization header if not using an anonymous connection
     */
    public function __construct($storageDir, $curl = null, $httpAuth = '')
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
     * @return bool
     */
    private function isPdfExportEnabled(): bool
    {
        return $this->configLoader->get('googledrive_export_pdf') == '1' && isset($metadata['exportLinks']['application/pdf']);
    }


    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int $fileId
     * @param bool $refresh
     * @return array
     */
    public function fetchMetaData($fileId, $refresh = false): array
    {
        if (empty($this->metaCache[$fileId]) || $refresh) {
            $ch = $this->curlPrepare(array(
                CURLOPT_URL            => self::GOOGLE_API . "/files/{$fileId}",
                CURLOPT_RETURNTRANSFER => 1,
            ));

            $this->metaCache[$fileId] = json_decode(curl_exec($ch), true);
            curl_close($ch);
        }

        return $this->metaCache[$fileId];
    }


    /**
     * Attempts to retrieve remote source to local destination, returns the path of the saved file.
     *
     * @param int $fileId
     * @throws OutOfBoundsException
     * @return string
     */
    public function fetchToFile($fileId): string
    {
        $metadata = $this->fetchMetaData($fileId);

        if (isset($metadata['labels']['restricted']) && $metadata['labels']['restricted'] == true) {
            // if this file has been flagged by Drive as "restricted", disallow any download
            return null;
        } elseif ($this->isPdfExportEnabled()) {
            // if there is a PDF-export option for this file (and the config is set to do pdf export), always give that preference
            $url = $metadata['exportLinks']['application/pdf'];
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
     * @param array $metadata
     * @return string
     */
    private function inspectExportLinks(array $metadata): string
    {
        if (isset($this->mimetypeMapping[$metadata['mimeType']])) {
            $key = $this->mimetypeMapping[$metadata['mimeType']][self::CONTEXT_EXPORTLINKS];

            return $metadata[self::CONTEXT_EXPORTLINKS][$key];
        }

        foreach (self::FIELDS_EXPORTLINKS as $field) {
            if (!empty($metadata[$field])) {
                return $metadata[$field];
            }
        }

        /* If you got here, the api no longer provides the fields we were originally looking at
         */
        trigger_error('Google api fields for exportLinks not found: check api docs', E_USER_WARNING);

        return '';
    }


    /**
     * Inspect metadata fields to find a file extension
     *
     * @param array $metadata
     * @return string
     */
    private function inspectFileExtension(array $metadata): string
    {
        if (isset($this->mimetypeMapping[$metadata['mimeType']])) {
            return $this->mimetypeMapping[$metadata['mimeType']][self::CONTEXT_EXTENSION];
        }

        foreach (self::FIELDS_EXTENSION as $field) {
            if (!empty($metadata[$field])) {
                return $metadata[$field];
            }
        }

        /* If you got here, the api no longer provides the fields we were originally looking at
         */
        trigger_error('Google api fields for extension not found: check api docs', E_USER_WARNING);

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
        if ($this->isPdfExportEnabled()) {
            // if there is a PDF-export option for this file (and the config is set to do pdf export), always give that preference
            $ext = 'pdf';
        } else {

            /* Inspect metadata fields to find a file extension
             */
            $ext = $this->inspectFileExtension($metadata);
        }

        return "{$metadata['title']}.{$ext}";
    }


    /**
     * @param array $opts
     * @return resource
     */
    protected function curlPrepare($opts)
    {
        $ch = curl_copy_handle($this->ch);
        curl_setopt_array($ch, $opts);
        return $ch;
    }
} 
