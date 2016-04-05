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

class GDriveFetcher implements FetcherInterface
{

    const GOOGLE_API = 'https://www.googleapis.com/drive/v2';


    /** @var resource */
    protected $ch;

    /** @var string */
    protected $dir;

    /** @var array */
    protected $metaCache = array();


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

        } elseif (isset($metadata['exportLinks']['application/pdf'])) {
            // if there is a PDF-export option for this file, always give that preference
            $url = $metadata['exportLinks']['application/pdf'];
            $ext = '.pdf';

        } else {
            // fall back to the download URL
            $url = $metadata['downloadUrl'];
            $ext = '';
        }

        if (!$url) {
            // no valid URL was found
            throw new OutOfBoundsException("No url found for download");
        }

        $destination = $this->getFirstFreeName($metadata['title'] . $ext);

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
