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

    const GOOGLE_API = 'https://www.googleapis.com/drive/v2';


    /** @var resource */
    protected $ch;

    /** @var string */
    protected $dir;

    /** @var array */
    protected $metaCache = array();

    /** @var ConfigLoader */
    protected $configLoader;

    public function getConfigLoader() {
        return $this->configLoader;
    }

    public function setConfigLoader($configLoader) {
        $this->configLoader = $configLoader;
        return $this;
    }

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

        $this->setConfigLoader(new ConfigLoader());
    }


    /**
     * Clean up the cURL handle
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * Retrieves the metadata for the given fileId
     *
     * @param int $fileId
     * @param bool $refresh
     * @return array
     */
    function fetchMetaData($fileId, $refresh = false)
    {
        if (empty($this->metaCache[$fileId]) || $refresh) {
            $ch = $this->cUrlPrepare(array(
                CURLOPT_URL            => self::GOOGLE_API . "/files/$fileId",
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
    function fetchToFile($fileId)
    {
        $metadata = $this->fetchMetaData($fileId);

        if (isset($metadata['labels']['restricted']) && $metadata['labels']['restricted'] == true) {
            // if this file has been flagged by Drive as "restricted", disallow any download
            $url = null;
            $ext = '';

        } elseif ($this->getConfigLoader()->get('googledrive_export_pdf') == '1' && isset($metadata['exportLinks']['application/pdf'])) {
            // if there is a PDF-export option for this file (and the config is set to do pdf export), always give that preference
            $url = $metadata['exportLinks']['application/pdf'];
            $ext = '.pdf';

        } else {

            if ($metadata['mimeType'] == "application/vnd.google-apps.presentation") {
                // Get PPTX format slides
                $url = $metadata['exportLinks']['application/vnd.openxmlformats-officedocument.presentationml.presentation'];
                $ext = '.pptx';
            } else if ($metadata['mimeType'] == "application/vnd.google-apps.document") {
                // Get DOCX format spreadsheet
                $url = $metadata['exportLinks']['application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $ext = '.docx';
            } else if ($metadata['mimeType'] == "application/vnd.google-apps.spreadsheet") {
                // Get XLSX format spreadsheet
                $url = $metadata['exportLinks']['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                $ext = '.xlsx';
            } else if (!empty($metadata['downloadUrl'])) {
                // Fall back to the download URL
                $url = $metadata['downloadUrl'];
                $ext = $this->deriveFileExtension($metadata);
            } else {
                // Last ditched PDF attempt if the download URL not set
                $url = $metadata['exportLinks']['application/pdf'];
                $ext = '.pdf';
            }

        }

        if (!$url) {
            // no valid URL was found
            throw new OutOfBoundsException("No url found for download");
        }

        $destination = $this->getFirstFreeName($this->parseTitle($metadata['title'], $ext));

        if (!($fp = fopen($destination, 'w'))) {
            throw new OutOfBoundsException("Could not open target");
        }

        $ch = $this->cUrlPrepare(array(
            CURLOPT_URL  => $url,
            CURLOPT_FILE => $fp,
        ));

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $destination;
    }


    /**
     * Derive the file extension from the metadata
     *
     * @param array $metadata
     * @return string
     */
    protected function deriveFileExtension(array $metadata)
    {
        if ($metadata['fullFileExtension']) {
            // attempt to use the file extension proper
            return '.' . $metadata['fullFileExtension'];
        }

        // nothing else we can really go on
        return '';
    }


    /**
     * Append the given extension to the title *if not already suffixed*
     *
     * @param string $title
     * @param string $ext
     * @return string
     */
    protected function parseTitle($title, $ext)
    {
        $titleLen = strlen($title);
        $extLen   = strlen($ext);
        if ($titleLen < 1) {
            throw new OutOfBoundsException("Title must contain characters");
        }

        // true if the extension is already suffixed
        $endsWith = substr_compare($title, $ext, $titleLen - $extLen, $extLen) === 0;

        if ($endsWith) {
            // title is correct, just return that
            return $title;
        }

        // append extension and return
        return $title . $ext;
    }


    /**
     * @param array $opts
     * @return resource
     */
    protected function cUrlPrepare($opts)
    {
        $ch = curl_copy_handle($this->ch);
        curl_setopt_array($ch, $opts);
        return $ch;
    }

    /**
     * @param $filename
     * @return string
     */
    protected function getFirstFreeName($filename)
    {
        if (($dot = strrpos($filename, '.')) === false) {
            $title = $filename;
            $ext   = '';
        } else {
            $title = substr($filename, 0, $dot);
            $ext   = substr($filename, $dot);
        }

        $i = 0;

        // iterate filenames until we find a name that hasn't been taken yet
        do {
            $destination = "{$this->dir}/$title" . ($i > 0 ? "-{$i}" : '') . $ext;
        } while (file_exists($destination) && ++$i);

        return $destination;
    }

} 
